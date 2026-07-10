import json
import os
from pathlib import Path

from playwright.sync_api import sync_playwright


BASE_URL = os.environ.get(
    "TABLERO_BASE_URL",
    "http://127.0.0.1/automatiza-tech/tablero/",
)
SCREENSHOT = Path(__file__).with_name("tablero-v8-test.png")
CHROME_PATH = os.environ.get(
    "PLAYWRIGHT_CHROME_PATH",
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
)

CLIENTE = {
    "at_id": "AT-CLI-TEST-001",
    "nombre": "Cliente Prueba",
    "contacto": "Contacto QA",
    "rubro": "QA",
    "servicios": ["Sitio web premium"],
    "paso": 3,
    "prioridad": "P2",
    "estado": "progress",
    "estadoLabel": "En prueba",
    "ultima": "2026-07-10",
    "notas": "Registro simulado por Playwright",
}

INTERNA = {
    "at_id": "AT-INT-TEST-001",
    "titulo": "Validar tablero v8",
    "asignadoA": "Codex",
    "tipo": "dev",
    "estado": "progress",
    "prioridad": "P1",
    "ultima": "2026-07-10",
    "notas": "Prueba automatizada",
}


def main() -> None:
    requests = []
    posts = []
    page_errors = []

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, executable_path=CHROME_PATH)
        context = browser.new_context(viewport={"width": 1440, "height": 1000})
        page = context.new_page()
        page.on("pageerror", lambda error: page_errors.append(str(error)))

        def api_route(route, request):
            headers = {
                "Access-Control-Allow-Origin": "http://127.0.0.1",
                "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
                "Access-Control-Allow-Headers": "Content-Type, Authorization, X-AT-Board-Token",
                "Content-Type": "application/json; charset=utf-8",
            }
            requests.append(
                {
                    "method": request.method,
                    "url": request.url,
                    "headers": dict(request.headers),
                }
            )
            if request.method == "OPTIONS":
                route.fulfill(status=204, headers=headers, body="")
                return
            if request.method == "POST":
                payload = request.post_data_json
                posts.append(payload)
                route.fulfill(
                    status=200,
                    headers=headers,
                    body=json.dumps(
                        {
                            "ok": True,
                            "data": {
                                "at_id": payload.get("at_id"),
                                "tipo": payload.get("tipo"),
                                "accion": "update",
                            },
                            "error": None,
                        }
                    ),
                )
                return
            route.fulfill(
                status=200,
                headers=headers,
                body=json.dumps(
                    {
                        "ok": True,
                        "data": {
                            "version": "v8",
                            "clientes": [CLIENTE],
                            "internas": [INTERNA],
                        },
                        "error": None,
                    }
                ),
            )

        page.route("https://automatizatech.cl/api-tablero.php", api_route)
        page.goto(BASE_URL, wait_until="networkidle")

        first_card = page.locator('.card[data-tipo="cli"]').first
        first_card.wait_for()
        assert first_card.get_attribute("draggable") is None

        page.locator("#btnToken").click()
        page.locator("#inputToken").fill("token-prueba-seguro")
        page.locator("#btnProbarToken").click()
        page.locator("#tokenTestResult", has_text="Conexión OK").wait_for()
        page.keyboard.press("Escape")

        page.locator("#btnSync").click()
        page.locator('.card[data-tipo="cli"]', has_text="Cliente Prueba").wait_for()

        get_request = next(item for item in requests if item["method"] == "GET")
        assert "?token=" not in get_request["url"]
        assert get_request["headers"].get("x-at-board-token") == "token-prueba-seguro"
        assert get_request["headers"].get("authorization") == "Bearer token-prueba-seguro"

        card = page.locator('.card[data-tipo="cli"]', has_text="Cliente Prueba")
        assert card.get_attribute("draggable") == "true"
        card.click()
        page.locator("#edCliNotas").fill("Edición verificada por Playwright")
        page.locator("#btnGuardarCli").click()
        page.locator("#modalCli").wait_for(state="hidden")

        assert posts, "La edición no generó un POST"
        assert posts[-1]["tipo"] == "cli"
        assert posts[-1]["nombre"] == "Cliente Prueba"
        assert posts[-1]["notas"] == "Edición verificada por Playwright"

        moved_card = page.locator('.card[data-tipo="cli"]', has_text="Cliente Prueba")
        moved_card.drag_to(page.locator('[data-colbody-cli="4"]'))
        page.wait_for_function("() => document.querySelector('[data-colbody-cli=\"4\"] .card[data-id=\"AT-CLI-TEST-001\"]') !== null")
        assert posts[-1]["tipo"] == "cli"
        assert posts[-1]["nombre"] == "Cliente Prueba"
        assert posts[-1]["paso"] == 4

        page.evaluate("guardarToken(''); actualizarTokenEstado(); renderClientes();")
        page.locator('.card[data-tipo="cli"]', has_text="Cliente Prueba").click()
        page.locator("#edCliNotas").fill("No debe persistir")
        before_posts = len(posts)
        page.locator("#btnGuardarCli").click()
        page.locator("#toast", has_text="Modo solo lectura").wait_for()
        assert len(posts) == before_posts

        page.screenshot(path=str(SCREENSHOT), full_page=True)
        assert not page_errors, f"Errores JavaScript: {page_errors}"
        browser.close()

    print(
        json.dumps(
            {
                "ok": True,
                "requests": len(requests),
                "posts": len(posts),
                "screenshot": str(SCREENSHOT),
            },
            ensure_ascii=False,
        )
    )


if __name__ == "__main__":
    main()
