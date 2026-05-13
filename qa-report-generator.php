<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * qa-report-generator.php
 * Genera un reporte .md consolidado de errores QA por proyecto.
 *
 * Uso: acceder por web (http://localhost/automatiza-tech/qa-report-generator.php)
 *      o ejecutar desde CLI: php qa-report-generator.php
 *
 * El reporte incluye:
 *   - Resumen ejecutivo (métricas a rellenar)
 *   - Resumen por módulo con conteo de casos
 *   - Registro de bugs encontrados (listo para llenar)
 *   - Matriz de ejecución completa (todos los casos de cada archivo QA)
 */

define('QA_BASE_DIR', __DIR__);
define('QA_CLIENTES_DIR', QA_BASE_DIR . '/Clientes');

$isCli = (php_sapi_name() === 'cli');
$generate = $isCli || isset($_POST['generate']);

// ─────────────────────────────────────────────────────────────────────────────
// Utilidades de parseo
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Extrae los casos de prueba de un archivo QA markdown.
 * Devuelve array de [ 'id', 'desc', 'priority', 'section' ]
 */
function parseQAFile(string $filePath): array {
    $content = file_get_contents($filePath);
    if ($content === false) return [];

    $lines       = explode("\n", $content);
    $cases       = [];
    $headers     = [];
    $inTable     = false;
    $section     = '';

    foreach ($lines as $raw) {
        $line = trim($raw);

        // Detectar encabezados de sección (## o ###)
        if (preg_match('/^#{2,3}\s+(.+)/', $line, $m)) {
            $section = trim($m[1]);
            $inTable = false;
            $headers = [];
            continue;
        }

        if ($line === '') {
            // Mantener estado de tabla; solo resetear si pasaron varias líneas vacías
            continue;
        }

        // Filas de tabla
        if ($line[0] === '|') {
            $cells = array_values(array_filter(
                array_map('trim', explode('|', $line)),
                fn($c) => $c !== ''
            ));

            // Fila separadora (---|---|---)
            if (empty($cells) || preg_match('/^[\s\-|:]+$/', $line)) {
                continue;
            }

            if (!$inTable) {
                // Primera fila de la tabla = cabeceras
                $headers = $cells;
                $inTable = true;
                continue;
            }

            // Fila de datos
            if (!empty($headers) && count($cells) >= 2) {
                $row  = [];
                foreach ($headers as $i => $h) {
                    $row[trim($h)] = $cells[$i] ?? '';
                }

                $id   = trim($row['ID'] ?? array_values($row)[0] ?? '');
                $desc = trim($row['Caso de Uso'] ?? $row['Caso'] ?? array_values($row)[1] ?? '');
                $prio = trim($row['Prioridad'] ?? '—');

                // Solo filas con IDs válidos (XX-NNN)
                if ($id !== '' && preg_match('/^[A-Z]{1,4}-\d{2,}/', $id)) {
                    // Sanitizar para markdown
                    $desc = preg_replace('/\s+/', ' ', str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $desc));
                    $desc = mb_strimwidth($desc, 0, 100, '…');

                    $cases[] = [
                        'id'       => $id,
                        'desc'     => $desc,
                        'priority' => $prio,
                        'section'  => $section,
                    ];
                }
            }
        } else {
            // Línea fuera de tabla: resetear estado
            if ($inTable) {
                $inTable = false;
                $headers = [];
            }
        }
    }

    return $cases;
}

/**
 * Obtiene el título del proyecto desde el archivo QA-00.
 */
function getProjectTitle(string $qaDir, string $fallback): string {
    $indexFile = $qaDir . '/QA-00-Indice-Entornos.md';
    if (!file_exists($indexFile)) return $fallback;

    $content = file_get_contents($indexFile, false, null, 0, 600);
    if (preg_match('/\*\*Proyecto:\*\*\s*(.+)/m', $content, $m)) {
        return trim($m[1]);
    }
    return $fallback;
}

// ─────────────────────────────────────────────────────────────────────────────
// Generador del reporte
// ─────────────────────────────────────────────────────────────────────────────

function generateReport(string $projectName, string $qaDir, string $projectTitle): string {
    $date = date('Y-m-d');
    $ts   = date('Y-m-d H:i:s');

    // Recopilar módulos (archivos QA-NN-*.md, excluir QA-00 e INFORMES)
    $files = glob($qaDir . '/QA-[0-9][0-9]-*.md');
    if (!$files) $files = [];
    sort($files);

    $modules    = [];
    $totalCases = 0;

    foreach ($files as $f) {
        $fname = basename($f);
        // Excluir índice, informes y reportes previos
        if (preg_match('/^QA-00/', $fname)) continue;
        if (stripos($fname, 'INFORME') !== false) continue;
        if (stripos($fname, 'REPORTE') !== false) continue;

        // Nombre legible del módulo a partir del nombre de archivo
        preg_match('/^QA-\d+-(.+)\.md$/', $fname, $m);
        $modLabel = isset($m[1]) ? str_replace('-', ' ', $m[1]) : $fname;

        $cases       = parseQAFile($f);
        $totalCases += count($cases);

        $modules[] = [
            'file'   => $fname,
            'label'  => $modLabel,
            'cases'  => $cases,
            'count'  => count($cases),
        ];
    }

    // ── Cabecera del reporte ──────────────────────────────────────────────────
    $md  = "# REPORTE DE ERRORES QA — {$projectTitle}\n\n";
    $md .= "> **Proyecto:** {$projectTitle}  \n";
    $md .= "> **Fecha de generación:** {$ts}  \n";
    $md .= "> **Entorno:** ☐ Desarrollo  ☐ Producción  \n";
    $md .= "> **Tester:** _____________  \n";
    $md .= "> **Versión probada:** _____________  \n\n";
    $md .= "---\n\n";

    // ── Diagrama de pastel (mermaid) ─────────────────────────────────────────
    $md .= "## 📊 RESUMEN EJECUTIVO\n\n";
    $md .= "```mermaid\n";
    $md .= "pie title Resultado de Casos de Prueba\n";
    $md .= "    \"✅ Pasados\"     : 0\n";
    $md .= "    \"❌ Fallidos\"    : 0\n";
    $md .= "    \"⚠️ Bloqueados\"  : 0\n";
    $md .= "    \"⏭️ No ejecutados\" : {$totalCases}\n";
    $md .= "```\n\n";
    $md .= "> Reemplaza los `0` con los valores reales al finalizar cada suite.\n\n";

    // ── Tabla de métricas ────────────────────────────────────────────────────
    $md .= "| Métrica | Valor |\n|---|---|\n";
    $md .= "| **Total casos planificados** | {$totalCases} |\n";
    $md .= "| **Casos ejecutados** | |\n";
    $md .= "| **✅ Pasados** | |\n";
    $md .= "| **❌ Fallidos** | |\n";
    $md .= "| **⚠️ Bloqueados** | |\n";
    $md .= "| **⏭️ No ejecutados** | |\n";
    $md .= "| **Tasa de éxito (Pass Rate)** | % |\n";
    $md .= "| **Total bugs reportados** | |\n";
    $md .= "| **🔴 Bugs Críticos** | |\n";
    $md .= "| **🟠 Bugs Mayores** | |\n";
    $md .= "| **🟡 Bugs Menores** | |\n";
    $md .= "| **⚪ Bugs Triviales** | |\n";
    $md .= "| **Veredicto final** | ☐ APROBADO  ☐ CONDICIONADO  ☐ RECHAZADO |\n\n";

    // ── Criterios de aprobación ──────────────────────────────────────────────
    $md .= "### Criterios de Aprobación\n\n";
    $md .= "| Criterio | Umbral | Resultado |\n|---|---|---|\n";
    $md .= "| Pass Rate general | ≥ 95% | |\n";
    $md .= "| Bugs Críticos abiertos | 0 | |\n";
    $md .= "| Bugs Mayores abiertos | ≤ 2 | |\n\n";
    $md .= "---\n\n";

    // ── Resumen por módulo ───────────────────────────────────────────────────
    $md .= "## 📂 RESUMEN POR MÓDULO\n\n";
    $md .= "| Módulo | Archivo | Total | ✅ Pass | ❌ Fail | ⚠️ Bloq. | Pass Rate |\n";
    $md .= "|---|---|---|---|---|---|---|\n";
    foreach ($modules as $mod) {
        $md .= "| {$mod['label']} | {$mod['file']} | {$mod['count']} | | | | |\n";
    }
    $md .= "| **TOTAL** | | **{$totalCases}** | | | | |\n\n";
    $md .= "---\n\n";

    // ── Registro de bugs ─────────────────────────────────────────────────────
    $md .= "## 🐛 REGISTRO DE ERRORES ENCONTRADOS\n\n";
    $md .= "> **Instrucciones:** Registra aquí cada error/falla encontrada durante la ejecución.  \n";
    $md .= "> Usa `BUG-XXX` como identificador único. Vincula el `Bug ID` en la matriz de ejecución.\n\n";
    $md .= "| Bug ID | Caso QA | Módulo | Severidad | Descripción del Error | Pasos para Reproducir | Resultado Real | Resultado Esperado | Estado | Asignado a |\n";
    $md .= "|---|---|---|---|---|---|---|---|---|---|\n";
    $md .= "| BUG-001 | | | 🔴 Crítico | | | | | Abierto | |\n";
    $md .= "| BUG-002 | | | 🟠 Mayor | | | | | Abierto | |\n";
    $md .= "| BUG-003 | | | 🟡 Menor | | | | | Abierto | |\n\n";
    $md .= "**Leyenda:** 🔴 Crítico (bloquea funcionalidad) · 🟠 Mayor (impacto alto) · 🟡 Menor (impacto medio) · ⚪ Trivial (cosmético)  \n";
    $md .= "**Estados:** Abierto · En revisión · Resuelto · Cerrado · No reproducible · Diferido\n\n";
    $md .= "---\n\n";

    // ── Matriz de ejecución por módulo ───────────────────────────────────────
    $md .= "## 📋 MATRIZ DE EJECUCIÓN DETALLADA\n\n";

    foreach ($modules as $mod) {
        $md .= "### {$mod['label']}\n\n";

        if (empty($mod['cases'])) {
            $md .= "_Sin casos de prueba detectados en {$mod['file']}._\n\n";
            continue;
        }

        $md .= "| ID | Caso de Uso | Prioridad | Resultado | Tester | Fecha | Bug ID | Observaciones |\n";
        $md .= "|---|---|---|---|---|---|---|---|\n";

        foreach ($mod['cases'] as $c) {
            $prio = match (strtolower($c['priority'])) {
                'alta'  => '🔴 Alta',
                'media' => '🟡 Media',
                'baja'  => '🟢 Baja',
                default => $c['priority'],
            };
            $md .= "| {$c['id']} | {$c['desc']} | {$prio} | ☐ PASS  ☐ FAIL  ☐ BLOQ | | | | |\n";
        }
        $md .= "\n";
    }

    $md .= "---\n\n";
    $md .= "_Reporte generado automáticamente por `qa-report-generator.php` el {$ts}_\n";

    return $md;
}

// ─────────────────────────────────────────────────────────────────────────────
// Detección de proyectos
// ─────────────────────────────────────────────────────────────────────────────

function getProjects(): array {
    $projects = [];
    if (!is_dir(QA_CLIENTES_DIR)) return $projects;

    foreach (glob(QA_CLIENTES_DIR . '/*', GLOB_ONLYDIR) as $dir) {
        $qaDir = $dir . '/QA';
        if (is_dir($qaDir)) {
            $name  = basename($dir);
            $title = getProjectTitle($qaDir, $name);
            $projects[] = [
                'name'   => $name,
                'title'  => $title,
                'qaDir'  => $qaDir,
            ];
        }
    }

    return $projects;
}

// ─────────────────────────────────────────────────────────────────────────────
// Ejecución principal
// ─────────────────────────────────────────────────────────────────────────────

$projects = getProjects();
$results  = [];

if ($generate) {
    foreach ($projects as $p) {
        $reportContent = generateReport($p['name'], $p['qaDir'], $p['title']);
        $outputFile    = $p['qaDir'] . '/QA-REPORTE-ERRORES-' . date('Y-m-d') . '.md';

        if (file_put_contents($outputFile, $reportContent) !== false) {
            $results[] = [
                'status' => 'ok',
                'file'   => $outputFile,
                'name'   => $p['name'],
                'title'  => $p['title'],
                'size'   => strlen($reportContent),
                'lines'  => substr_count($reportContent, "\n"),
            ];
        } else {
            $results[] = [
                'status' => 'error',
                'file'   => $outputFile,
                'name'   => $p['name'],
                'title'  => $p['title'],
            ];
        }

        if ($isCli) {
            $r = end($results);
            if ($r['status'] === 'ok') {
                echo "✅ {$r['name']}: {$r['file']} ({$r['lines']} líneas)\n";
            } else {
                echo "❌ {$r['name']}: no se pudo escribir {$r['file']}\n";
            }
        }
    }

    if ($isCli) {
        echo "\n📊 " . count(array_filter($results, fn($r) => $r['status'] === 'ok')) . " reporte(s) generado(s).\n";
        exit(0);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// UI Web
// ─────────────────────────────────────────────────────────────────────────────
if ($isCli) exit(0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Report Generator — AutomatizaTech</title>
    <style>
        :root {
            --primary: #4F46E5;
            --success: #16a34a;
            --danger:  #dc2626;
            --bg:      #f8fafc;
            --card:    #ffffff;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: #1e293b;
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        .container { max-width: 860px; margin: 0 auto; }
        header {
            background: linear-gradient(135deg, var(--primary), #7C3AED);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        header h1 { font-size: 1.5rem; font-weight: 700; }
        header p  { font-size: 0.9rem; opacity: 0.85; margin-top: 0.3rem; }
        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card h2 { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .project-list { display: grid; gap: .75rem; }
        .project-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px;
            background: #f8fafc;
        }
        .project-item strong { font-size: 1rem; }
        .project-item span   { font-size: .8rem; color: #64748b; }
        .badge {
            font-size: .75rem; padding: .2rem .6rem; border-radius: 9999px; font-weight: 600;
        }
        .badge-indigo { background: #eef2ff; color: var(--primary); }
        form { margin-top: 1.5rem; }
        button[type=submit] {
            background: var(--primary); color: white; border: none; cursor: pointer;
            padding: .75rem 2rem; border-radius: 8px; font-size: 1rem; font-weight: 600;
            transition: background .2s;
        }
        button[type=submit]:hover { background: #4338ca; }
        .results { margin-top: 1.5rem; display: grid; gap: .75rem; }
        .result-ok, .result-err {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1rem; border-radius: 8px; font-size: .9rem;
        }
        .result-ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .result-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .result-ok  .icon { font-size: 1.2rem; }
        .result-err .icon { font-size: 1.2rem; }
        .path { font-size: .78rem; font-family: monospace; word-break: break-all; opacity: .8; }
        .meta { font-size: .78rem; opacity: .7; margin-top: .2rem; }
        footer { text-align: center; font-size: .8rem; color: #94a3b8; margin-top: 2rem; }
    </style>
</head>
<body>
<div class="container">

    <header>
        <h1>📋 QA Report Generator</h1>
        <p>AutomatizaTech — Genera reportes de errores QA en formato Markdown por proyecto</p>
    </header>

    <!-- Proyectos detectados -->
    <div class="card">
        <h2>Proyectos detectados</h2>
        <div class="project-list">
        <?php foreach ($projects as $p): ?>
            <?php
                $qaFiles = glob($p['qaDir'] . '/QA-[0-9][0-9]-*.md');
                $qaFiles = array_filter($qaFiles ?? [], fn($f) => !preg_match('/QA-00/', basename($f)) && stripos(basename($f), 'INFORME') === false && stripos(basename($f), 'REPORTE') === false);
                $modCount = count($qaFiles);
            ?>
            <div class="project-item">
                <div>
                    <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                    <span><?= htmlspecialchars($p['qaDir']) ?></span>
                </div>
                <span class="badge badge-indigo"><?= $modCount ?> módulo<?= $modCount !== 1 ? 's' : '' ?></span>
            </div>
        <?php endforeach; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="generate" value="1">
            <button type="submit">⚡ Generar Reportes .md</button>
        </form>
    </div>

    <?php if ($generate && !empty($results)): ?>
    <!-- Resultados de generación -->
    <div class="card">
        <h2>Reportes generados</h2>
        <div class="results">
        <?php foreach ($results as $r): ?>
            <?php if ($r['status'] === 'ok'): ?>
            <div class="result-ok">
                <span class="icon">✅</span>
                <div>
                    <strong><?= htmlspecialchars($r['title']) ?></strong>
                    <div class="path"><?= htmlspecialchars($r['file']) ?></div>
                    <div class="meta"><?= number_format($r['size']) ?> caracteres · <?= number_format($r['lines']) ?> líneas</div>
                </div>
            </div>
            <?php else: ?>
            <div class="result-err">
                <span class="icon">❌</span>
                <div>
                    <strong><?= htmlspecialchars($r['name']) ?></strong> — Error al escribir archivo
                    <div class="path"><?= htmlspecialchars($r['file']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <footer>qa-report-generator.php · AutomatizaTech · <?= date('Y') ?></footer>
</div>
</body>
</html>
