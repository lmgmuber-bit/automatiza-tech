{
  "name": "WhatsApp Tech - Principal (PROD)",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "whatsapp-webhook-tech",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1.1,
      "position": [
        74608,
        22912
      ],
      "id": "1ebffd0b-a157-4c48-9b77-c6417b1c6e31",
      "name": "WhatsApp Webhook",
      "webhookId": "whatsapp-webhook-tech"
    },
    {
      "parameters": {
        "path": "whatsapp-webhook-tech",
        "responseMode": "responseNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1.1,
      "position": [
        74608,
        22400
      ],
      "id": "3d762b02-fc74-4030-bb50-d409acfbe1a2",
      "name": "Verify Webhook (GET)",
      "webhookId": "whatsapp-webhook-tech-verify"
    },
    {
      "parameters": {
        "respondWith": "text",
        "responseBody": "={{ $json.query['hub.challenge'] }}",
        "options": {}
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [
        74832,
        22400
      ],
      "id": "6355a0d3-7f99-41f9-8fe6-52a62b215b41",
      "name": "Respond Challenge"
    },
    {
      "parameters": {
        "conditions": {
          "boolean": [
            {
              "value1": "={{ $json.body.entry[0].changes[0].value.messages !== undefined && $json.body.entry[0].changes[0].value.messages.length > 0 }}",
              "value2": true
            }
          ]
        }
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 1,
      "position": [
        74832,
        22912
      ],
      "id": "fd5afad1-1ada-48b4-9adc-44188dc55839",
      "name": "Has Message?"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "phoneNumber",
              "value": "={{ $json.body.entry[0].changes[0].value.messages[0].from }}"
            },
            {
              "name": "messageId",
              "value": "={{ $json.body.entry[0].changes[0].value.messages[0].id }}"
            },
            {
              "name": "messageType",
              "value": "={{ $json.body.entry[0].changes[0].value.messages[0].type }}"
            },
            {
              "name": "timestamp",
              "value": "={{ $json.body.entry[0].changes[0].value.messages[0].timestamp }}"
            },
            {
              "name": "contactName",
              "value": "={{ $json.body.entry[0].changes[0].value.contacts[0].profile.name }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $json.body.entry[0].changes[0].value.metadata.phone_number_id }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        75056,
        22912
      ],
      "id": "fb835a33-d33e-4bd7-9e47-a533a1a32389",
      "name": "Extract Message Data"
    },
    {
      "parameters": {
        "jsCode": "const messageId = $input.first().json.messageId;\nconst staticData = $getWorkflowStaticData('global');\n\nif (!staticData.processedIds) {\n  staticData.processedIds = [];\n}\n\n// Check if ID exists\nif (staticData.processedIds.includes(messageId)) {\n  return []; // Stop execution\n}\n\n// Add ID to list\nstaticData.processedIds.push(messageId);\n\n// Keep list size manageable\nif (staticData.processedIds.length > 1000) {\n  staticData.processedIds.shift();\n}\n\nreturn [{\n  json: $input.first().json\n}];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        75168,
        22912
      ],
      "id": "6f13c688-3f18-4c20-bbc6-3782f09eb7a3",
      "name": "Deduplication"
    },
    {
      "parameters": {
        "dataType": "string",
        "value1": "={{ $json.messageType }}",
        "rules": {
          "rules": [
            {
              "value2": "text"
            },
            {
              "value2": "audio",
              "output": 1
            },
            {
              "value2": "image",
              "output": 2
            },
            {
              "value2": "interactive",
              "output": 3
            }
          ]
        },
        "fallbackOutput": 4
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 1,
      "position": [
        75280,
        22880
      ],
      "id": "d6c4677a-6365-40f5-a879-e13e289f638c",
      "name": "Message Type"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "textContent",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].text.body }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Extract Message Data'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Extract Message Data'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Extract Message Data'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        75504,
        22624
      ],
      "id": "5b6218c3-76b5-4931-b171-b2d5b3a7082e",
      "name": "Process Text"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "audioId",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].audio.id }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Extract Message Data'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Extract Message Data'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Extract Message Data'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        75504,
        22816
      ],
      "id": "fbf58141-f06d-4edc-a7b9-a76c3966212b",
      "name": "Process Audio"
    },
    {
      "parameters": {
        "url": "=https://graph.facebook.com/v22.0/{{ $json.audioId }}",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        75728,
        22816
      ],
      "id": "0de9d0d4-2f2d-4091-a189-252431b4de08",
      "name": "Get Audio URL",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "url": "={{ $json.url }}",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "options": {
          "response": {
            "response": {
              "responseFormat": "file"
            }
          }
        }
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        75952,
        22816
      ],
      "id": "162df871-cc58-4da4-83f5-598e4cc7a6a7",
      "name": "Download Audio",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "resource": "audio",
        "operation": "transcribe",
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.openAi",
      "typeVersion": 1.3,
      "position": [
        76176,
        22816
      ],
      "id": "fc6bb3f6-61d3-43ce-b871-c355cbe3aa10",
      "name": "Transcribe Audio (Whisper)",
      "credentials": {
        "openAiApi": {
          "id": "g52IEXpRfN5r7jKw",
          "name": "OpenAi account"
        }
      }
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "textContent",
              "value": "={{ $json.text }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Process Audio'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Process Audio'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Process Audio'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76400,
        22816
      ],
      "id": "ba35e9b2-5d0a-4829-873c-4070bdd8e79f",
      "name": "Audio to Text"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "imageId",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].image.id }}"
            },
            {
              "name": "imageCaption",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].image.caption || 'Sin descripciÃ³n' }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Extract Message Data'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Extract Message Data'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Extract Message Data'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        75504,
        23008
      ],
      "id": "a118fe10-4b73-436c-84c7-291bb3578456",
      "name": "Process Image"
    },
    {
      "parameters": {
        "url": "=https://graph.facebook.com/v22.0/{{ $json.imageId }}?_nocache={{ $now.toMillis() }}",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        75728,
        23008
      ],
      "id": "7d779d3a-0377-494b-ad65-01f8aa1a5f55",
      "name": "Get Image URL",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "url": "={{ $json.url }}",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "options": {
          "response": {
            "response": {
              "responseFormat": "file"
            }
          },
          "timeout": 30000
        }
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        75952,
        23008
      ],
      "id": "cfa1583a-8555-4ef2-b559-93f7fb9a68e7",
      "name": "Download Image",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "const binaryData = $input.first().binary.data;\nconst base64Image = binaryData.data;\nconst mimeType = binaryData.mimeType || 'image/jpeg';\n\nreturn [{\n  json: {\n    imageBase64: `data:${mimeType};base64,${base64Image}`,\n    phoneNumber: $input.first().json.phoneNumber || $node['Process Image'].json.phoneNumber,\n    contactName: $input.first().json.contactName || $node['Process Image'].json.contactName,\n    phoneNumberId: $input.first().json.phoneNumberId || $node['Process Image'].json.phoneNumberId,\n    imageCaption: $input.first().json.imageCaption || $node['Process Image'].json.imageCaption,\n    imageId: $input.first().json.imageId || $node['Process Image'].json.imageId\n  }\n}];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        76128,
        23008
      ],
      "id": "82e5a118-1483-486f-9410-c21e09be464d",
      "name": "Prepare Base64 Image"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://api.openai.com/v1/chat/completions",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "openAiApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"model\": \"gpt-4o\",\n  \"messages\": [\n    {\n      \"role\": \"user\",\n      \"content\": [\n        {\n          \"type\": \"text\",\n          \"text\": \"Describe EXACTAMENTE lo que ves en esta imagen. LEE Y TRANSCRIBE TODO EL TEXTO VISIBLE en español. NO asumas nada. NO inventes contenido.\"\n        },\n        {\n          \"type\": \"image_url\",\n          \"image_url\": {\n            \"url\": \"{{ $json.imageBase64 }}\"\n          }\n        }\n      ]\n    }\n  ],\n  \"max_tokens\": 500\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76320,
        23008
      ],
      "id": "e5da4125-583a-4d7b-921c-31fbdeee9ecd",
      "name": "Analyze Image (Vision)",
      "credentials": {
        "openAiApi": {
          "id": "g52IEXpRfN5r7jKw",
          "name": "OpenAi account"
        }
      },
      "onError": "continueRegularOutput"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "textContent",
              "value": "=[IMAGEN ENVIADA - IGNORA CUALQUIER IMAGEN ANTERIOR] Descripción visual ACTUAL ({{ $now }}): {{ ($json.message?.content || $json.choices?.[0]?.message?.content || $json.content || $json.output || '').length > 5 ? ($json.message?.content || $json.choices?.[0]?.message?.content || $json.content || $json.output) : 'DEBUG_JSON: ' + JSON.stringify($json) }}. Caption original: {{ $node['Process Image'].json.imageCaption }} (ID: {{ $node['Process Image'].json.imageId }})"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Process Image'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Process Image'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Process Image'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76400,
        23008
      ],
      "id": "e121245d-276f-4715-9709-25b0b2a6a086",
      "name": "Image to Text"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "interactiveType",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].interactive.type }}"
            },
            {
              "name": "buttonId",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].interactive.button_reply?.id || $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].interactive.list_reply?.id || '' }}"
            },
            {
              "name": "buttonTitle",
              "value": "={{ $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].interactive.button_reply?.title || $node['WhatsApp Webhook'].json.body.entry[0].changes[0].value.messages[0].interactive.list_reply?.title || '' }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Extract Message Data'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Extract Message Data'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Extract Message Data'].json.phoneNumberId }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76400,
        23232
      ],
      "id": "0e284b37-8f77-48b1-b8e7-e01cb6e217b2",
      "name": "Process Interactive"
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.buttonId }}",
                    "rightValue": "btn_agendar_demo",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Agendar Demo"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.buttonId }}",
                    "rightValue": "btn_ver_planes",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Ver Planes"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.buttonId }}",
                    "rightValue": "btn_soporte",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Soporte"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.buttonId }}",
                    "rightValue": "day_",
                    "operator": {
                      "type": "string",
                      "operation": "startsWith"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Día Seleccionado"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.buttonId }}",
                    "rightValue": "time_",
                    "operator": {
                      "type": "string",
                      "operation": "startsWith"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Hora Seleccionada"
            }
          ]
        },
        "options": {
          "fallbackOutput": "extra"
        }
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        76592,
        23232
      ],
      "id": "27798976-337f-404c-8d99-cbf826569445",
      "name": "Button Action"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: $json.output } }) }}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77872,
        22576
      ],
      "id": "344db4ed-bf6f-44ac-985a-4e8321007817",
      "name": "Send WhatsApp Response",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "url": "https://automatizatech.cl/wp-json/automatiza-tech/v1/exchange-rate",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76848,
        22624
      ],
      "id": "07517ae0-7b53-4df7-8bce-e2e6a2bef0f0",
      "name": "Get Exchange Rate"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "chatInput",
              "value": "={{ $node['Concat Messages'].json.textContent }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Concat Messages'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Concat Messages'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Concat Messages'].json.phoneNumberId }}"
            }
          ],
          "number": [
            {
              "name": "rate",
              "value": "={{ $json.rate }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        77072,
        22624
      ],
      "id": "2c249868-20c5-465e-b58e-a1bafffb0770",
      "name": "Merge Data"
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "={{ $json.chatInput }}",
        "options": {
          "systemMessage": "=Eres Tech, el asistente virtual experto de AutomatizaTech en WhatsApp. Tu misión es asesorar a clientes sobre automatización de procesos, sitios web y chatbots inteligentes que funcionan 24/7.\n\nTU IDENTIDAD Y PERSONALIDAD\n- Nombre: Tech\n- Empresa: AutomatizaTech\n- Canal: WhatsApp Business\n- Tono: Profesional, tecnológico pero cercano y amable.\n- Estilo: Conciso, resolutivo y orientado a la venta consultiva.\n\nMANEJO DE IMÁGENES (CRÍTICO)\nSi recibes un mensaje que comienza con \"[IMAGEN ENVIADA...]\", esa es la ÚNICA verdad sobre lo que el usuario está viendo AHORA MISMO.\nOLVIDA cualquier imagen anterior mencionada en el chat. Solo importa la descripción visual ACTUAL.\nSi la descripción dice \"Integraciones Disponibles\", \"CRM\", \"WhatsApp\", etc., HABLA DE ESO.\nNO asumas que es una imagen de OpenAI a menos que la descripción ACTUAL lo diga explícitamente.\n\nTUS CAPACIDADES\n1. Explicar servicios y planes de forma clara.\n2. Calcular precios en tiempo real (USD a CLP).\n3. Resolver dudas frecuentes sobre automatización.\n4. Guiar al usuario para agendar, cancelar o reprogramar citas.\n5. Brindar soporte técnico básico a clientes existentes.\n\nREGLAS DE PRECIOS\n1. Tipo de Cambio Actual: ${{ $json.rate }} CLP por USD.\n2. Formato: Muestra siempre: $PRECIO_USD USD (aprox. $PRECIO_CLP CLP).\n\nNUESTROS SERVICIOS Y PLANES\n\n1. PAQUETE INICIAL: Sitio Web + WhatsApp Business\n- $299 USD (único) + $100 USD/mes (mantención).\n\n2. PLANES DE SUSCRIPCIÓN:\n- Básico: $99 USD/mes - Hasta 1,000 conversaciones\n- Profesional: $199 USD/mes - Hasta 5,000 conversaciones\n- Enterprise: $399 USD/mes - Ilimitado + soporte 24/7\n\nREGLAS DE COMUNICACIÓN WHATSAPP\n1. Brevedad: Máximo 1-2 párrafos por mensaje.\n2. Emojis: Usa emojis con moderación.\n3. Sin formato complejo: Solo texto simple y emojis.\n4. Saludo: Solo en el primer mensaje.\n\n⚠️ ACCIONES ESPECIALES - OBLIGATORIO ⚠️\nES OBLIGATORIO incluir UNA etiqueta de acción cuando el usuario:\n- Quiere AGENDAR demo/reunión → DEBES agregar: <<ACTION:SHOW_CALENDAR>>\n- Quiere CANCELAR cita → DEBES agregar: <<ACTION:CANCEL_APPOINTMENT>>\n- Quiere REPROGRAMAR cita → DEBES agregar: <<ACTION:RESCHEDULE_APPOINTMENT>>\n- Necesita SOPORTE técnico → DEBES agregar: <<ACTION:ESCALATE_SUPPORT>>\n- Quiere ver PLANES/precios → DEBES agregar: <<ACTION:SHOW_PLANS>>\n\nLA ETIQUETA VA AL FINAL DE TU MENSAJE, SIEMPRE.\n\nEJEMPLOS CORRECTOS:\n- Usuario: \"quiero agendar una demo\"\n- Tú: \"¡Excelente! Te muestro las opciones disponibles para agendar tu demo 📅 <<ACTION:SHOW_CALENDAR>>\"\n\n- Usuario: \"cuánto cuestan los planes?\"\n- Tú: \"¡Con gusto te muestro nuestros planes! 💼 <<ACTION:SHOW_PLANS>>\"\n\n- Usuario: \"necesito cancelar mi cita\"\n- Tú: \"Entendido, te ayudo con la cancelación. <<ACTION:CANCEL_APPOINTMENT>>\"\n\nSI NO INCLUYES LA ETIQUETA, EL USUARIO NO VERÁ LOS BOTONES.\n\nCONTACTO\n- Web: https://www.automatizatech.cl\n- WhatsApp: +56 9 4033 1127\n- Email: contacto@automatizatech.cl\n- Instagram: @automatizaTech.cl\n\nOBJETIVO\nGuiar al usuario para que entienda el valor de la automatización y quiera agendar una demo o contratar un plan."
        }
      },
      "type": "@n8n/n8n-nodes-langchain.agent",
      "typeVersion": 1.6,
      "position": [
        77072,
        22912
      ],
      "id": "815e9299-9614-4327-8812-8d9a883cf972",
      "name": "Agente IA - Tech WhatsApp"
    },
    {
      "parameters": {
        "model": "gpt-4o",
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        77088,
        23136
      ],
      "id": "8b9397f1-f0e0-46a8-a0a6-3f6cb795f143",
      "name": "Cerebro GPT-4o",
      "credentials": {
        "openAiApi": {
          "id": "g52IEXpRfN5r7jKw",
          "name": "OpenAi account"
        }
      }
    },
    {
      "parameters": {
        "sessionIdType": "customKey",
        "sessionKey": "={{ ($(\"Merge Data\").isExecuted ? $(\"Merge Data\").first().json.phoneNumber : undefined) || ($(\"Merge Audio Data\").isExecuted ? $(\"Merge Audio Data\").first().json.phoneNumber : undefined) || ($(\"Merge Image Data\").isExecuted ? $(\"Merge Image Data\").first().json.phoneNumber : undefined) || ($(\"Button to Text\").isExecuted ? $(\"Button to Text\").first().json.phoneNumber : undefined) || 'default' }}",
        "contextWindowLength": 30
      },
      "type": "@n8n/n8n-nodes-langchain.memoryBufferWindow",
      "typeVersion": 1.2,
      "position": [
        77392,
        23104
      ],
      "id": "6845a1b1-1b9e-4b39-95a9-5158128337a5",
      "name": "Memory Buffer (by Phone)"
    },
    {
      "parameters": {
        "jsCode": "// Detectar acciones especiales en la respuesta del AI\nconst output = $json.output || '';\n\n// Función helper para obtener datos de nodos que pueden no estar ejecutados\nfunction getFromNode(nodeName, field) {\n  try {\n    const node = $(nodeName);\n    if (node && node.first && node.first()) {\n      return node.first().json[field];\n    }\n  } catch (e) {\n    // Nodo no ejecutado\n  }\n  return null;\n}\n\n// Obtener phoneNumber y phoneNumberId de cualquier fuente disponible\nconst phoneNumber = $json.phoneNumber || \n  getFromNode('Merge Data', 'phoneNumber') || \n  getFromNode('Merge Audio Data', 'phoneNumber') || \n  getFromNode('Merge Image Data', 'phoneNumber') || \n  getFromNode('Button to Text', 'phoneNumber');\n\nconst phoneNumberId = $json.phoneNumberId || \n  getFromNode('Merge Data', 'phoneNumberId') || \n  getFromNode('Merge Audio Data', 'phoneNumberId') || \n  getFromNode('Merge Image Data', 'phoneNumberId') || \n  getFromNode('Button to Text', 'phoneNumberId');\n\nlet action = 'send_text';\nlet cleanMessage = output;\n\n// Detectar acciones especiales\nif (output.includes('<<ACTION:SHOW_CALENDAR>>')) {\n  action = 'show_calendar';\n  cleanMessage = output.replace('<<ACTION:SHOW_CALENDAR>>', '').trim();\n} else if (output.includes('<<ACTION:CANCEL_APPOINTMENT>>')) {\n  action = 'cancel_appointment';\n  cleanMessage = output.replace('<<ACTION:CANCEL_APPOINTMENT>>', '').trim();\n} else if (output.includes('<<ACTION:RESCHEDULE_APPOINTMENT>>')) {\n  action = 'reschedule_appointment';\n  cleanMessage = output.replace('<<ACTION:RESCHEDULE_APPOINTMENT>>', '').trim();\n} else if (output.includes('<<ACTION:ESCALATE_SUPPORT>>')) {\n  action = 'escalate_support';\n  cleanMessage = output.replace('<<ACTION:ESCALATE_SUPPORT>>', '').trim();\n} else if (output.includes('<<ACTION:SHOW_PLANS>>')) {\n  action = 'show_plans';\n  cleanMessage = output.replace('<<ACTION:SHOW_PLANS>>', '').trim();\n}\n\nreturn {\n  json: {\n    action: action,\n    output: cleanMessage,\n    phoneNumber: phoneNumber,\n    phoneNumberId: phoneNumberId\n  }\n};"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        77424,
        22912
      ],
      "id": "70806c53-1478-48eb-8436-23376fc0f698",
      "name": "Parse Response Actions"
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "send_text",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Texto Normal"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "show_calendar",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Calendario"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "cancel_appointment",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Cancelar"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "reschedule_appointment",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Reprogramar"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "escalate_support",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Soporte"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "show_plans",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Planes"
            }
          ]
        },
        "options": {
          "fallbackOutput": "none"
        }
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        77648,
        22912
      ],
      "id": "cd022c49-b250-4790-9a42-ed7d1d482a2b",
      "name": "Route Action"
    },
    {
      "parameters": {
        "jsCode": "// Generar los próximos 3 días hábiles (Lun-Vie)\nconst input = $input.first().json;\nconst now = DateTime.now().setZone('America/Santiago');\nconst days = [];\nlet checkDate = now;\n\nwhile (days.length < 3) {\n  checkDate = checkDate.plus({ days: 1 });\n  const dayOfWeek = checkDate.weekday;\n  \n  // Solo días hábiles (1-5 = Lun-Vie)\n  if (dayOfWeek >= 1 && dayOfWeek <= 5) {\n    const dayNames = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];\n    days.push({\n      date: checkDate.toFormat('yyyy-MM-dd'),\n      display: `${dayNames[dayOfWeek]} ${checkDate.toFormat('dd/MM')}`,\n      btnId: `day_${checkDate.toFormat('yyyy-MM-dd')}`\n    });\n  }\n}\n\nreturn {\n  json: {\n    ...input,\n    availableDays: days\n  }\n};"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        77872,
        22752
      ],
      "id": "0da741f6-ea30-4210-a7b6-ce6e86c5bf15",
      "name": "Generate Days"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"{{ $json.output.replace(/\"/g, '\\\\\"').replace(/\\n/g, '\\\\n').substring(0, 800) }}\\n\\n📅 Selecciona el día que prefieras:\"\n    },\n    \"action\": {\n      \"buttons\": [\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableDays[0].btnId }}\",\n            \"title\": \"{{ $json.availableDays[0].display }}\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableDays[1].btnId }}\",\n            \"title\": \"{{ $json.availableDays[1].display }}\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableDays[2].btnId }}\",\n            \"title\": \"{{ $json.availableDays[2].display }}\"\n          }\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        78096,
        22752
      ],
      "id": "6c796ee9-d79d-40d8-b204-214c0081e282",
      "name": "Send Calendar Buttons",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"{{ $json.output.replace(/\"/g, '\\\\\"').replace(/\\n/g, '\\\\n').substring(0, 1000) }}\\n\\n⚠️ ¿Confirmas la cancelación?\"\n    },\n    \"action\": {\n      \"buttons\": [\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"btn_cancelar_cita\",\n            \"title\": \"Sí, cancelar\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"btn_reprogramar\",\n            \"title\": \"Reprogramar\"\n          }\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77872,
        22912
      ],
      "id": "afb53455-1818-4a18-a377-96d97e4ffbea",
      "name": "Send Cancel Buttons",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: $json.output + '\\n\\n🔧 Tu caso ha sido escalado a nuestro equipo de soporte. Te contactaremos pronto.' } }) }}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77872,
        23088
      ],
      "id": "e9442e09-62a8-44c2-87c9-3491dec259e3",
      "name": "Send Support Escalation",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"{{ $json.output.replace(/\"/g, '\\\\\"').replace(/\\n/g, '\\\\n').substring(0, 1000) }}\\n\\n💼 Selecciona un plan:\"\n    },\n    \"action\": {\n      \"buttons\": [\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"btn_plan_basico\",\n            \"title\": \"Plan Básico $99\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"btn_plan_pro\",\n            \"title\": \"Plan Pro $199\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"btn_plan_enterprise\",\n            \"title\": \"Enterprise $399\"\n          }\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77872,
        23248
      ],
      "id": "588116d8-ef5b-4a89-ab8b-d31aa14d47f7",
      "name": "Send Plans Buttons",
      "credentials": {
        "whatsAppBusinessCloudApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        },
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "url": "https://automatizatech.cl/wp-json/automatiza-tech/v1/exchange-rate",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76624,
        22816
      ],
      "id": "b126d0f2-e11e-468f-9f97-c8d296aa33fb",
      "name": "Get Exchange Rate Audio"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "chatInput",
              "value": "=[AUDIO TRANSCRITO] {{ $node['Audio to Text'].json.textContent }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Audio to Text'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Audio to Text'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Audio to Text'].json.phoneNumberId }}"
            }
          ],
          "number": [
            {
              "name": "rate",
              "value": "={{ $json.rate }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76848,
        22816
      ],
      "id": "f347f15b-9c92-45ff-b2a0-8dae6ced0c44",
      "name": "Merge Audio Data"
    },
    {
      "parameters": {
        "url": "https://automatizatech.cl/wp-json/automatiza-tech/v1/exchange-rate",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76624,
        23008
      ],
      "id": "cbeba6fa-643d-4bef-8459-086ce6dd2299",
      "name": "Get Exchange Rate Image"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "chatInput",
              "value": "={{ $node['Image to Text'].json.textContent }}"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Image to Text'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Image to Text'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Image to Text'].json.phoneNumberId }}"
            }
          ],
          "number": [
            {
              "name": "rate",
              "value": "={{ $json.rate }}"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76848,
        23008
      ],
      "id": "125355cf-46d5-4b99-bad6-6b583887febb",
      "name": "Merge Image Data"
    },
    {
      "parameters": {
        "values": {
          "string": [
            {
              "name": "chatInput",
              "value": "=[BOTÃ“N PRESIONADO] El usuario presionÃ³: {{ $node['Process Interactive'].json.buttonTitle }} (ID: {{ $node['Process Interactive'].json.buttonId }})"
            },
            {
              "name": "phoneNumber",
              "value": "={{ $node['Process Interactive'].json.phoneNumber }}"
            },
            {
              "name": "contactName",
              "value": "={{ $node['Process Interactive'].json.contactName }}"
            },
            {
              "name": "phoneNumberId",
              "value": "={{ $node['Process Interactive'].json.phoneNumberId }}"
            }
          ],
          "number": [
            {
              "name": "rate",
              "value": "=950"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 2,
      "position": [
        76848,
        23232
      ],
      "id": "97872824-63be-4713-843e-97b6ba83ce90",
      "name": "Button to Text"
    },
    {
      "parameters": {
        "operation": "push",
        "list": "={{ $json.phoneNumber }}",
        "messageData": "={{ JSON.stringify({ message: $json.textContent, sessionID: $node['Extract Message Data'].json.messageId, date_time: new Date().toISOString() }) }}",
        "tail": true
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        75728,
        22624
      ],
      "id": "b6681368-ed9c-4499-b616-23cca16dc2f2",
      "name": "Redis Push",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "operation": "get",
        "propertyName": "Mensaje",
        "key": "={{ $('Process Text').item.json.phoneNumber }}",
        "options": {}
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        75952,
        22624
      ],
      "id": "ed943c37-463b-4ba3-9586-b8ae2a21d236",
      "name": "Redis Get",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ JSON.parse($json.Mensaje.filter(m => m.trim().startsWith('{')).slice(-1)[0]).sessionID }}",
                    "rightValue": "={{ $('Redis Push').item.json.messageData ? JSON.parse($('Redis Push').item.json.messageData).sessionID : $node['Extract Message Data'].json.messageId }}",
                    "operator": {
                      "type": "string",
                      "operation": "notEquals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Ignorar"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ new Date(JSON.parse($json.Mensaje.filter(m => m.trim().startsWith('{')).slice(-1)[0]).date_time) }}",
                    "rightValue": "={{ new Date(Date.now() - 7000) }}",
                    "operator": {
                      "type": "dateTime",
                      "operation": "before"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Procesar"
            }
          ]
        },
        "options": {
          "fallbackOutput": "extra",
          "renameFallbackOutput": "Esperar"
        }
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3.2,
      "position": [
        76176,
        22624
      ],
      "id": "8918422a-1784-4c9e-a11e-1c4cec1a2651",
      "name": "Check Message Status"
    },
    {
      "parameters": {
        "operation": "delete",
        "key": "={{ $('Process Text').item.json.phoneNumber }}"
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        76400,
        22624
      ],
      "id": "dbdeef4e-15d3-46c1-9ca2-df0d4eed5362",
      "name": "Redis Delete",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "mensaje-concat-id",
              "name": "textContent",
              "value": "={{ $('Redis Get').item.json.Mensaje.map(m => JSON.parse(m).message).join(' ') }}",
              "type": "string"
            },
            {
              "id": "phone-number-id",
              "name": "phoneNumber",
              "value": "={{ $('Process Text').item.json.phoneNumber }}",
              "type": "string"
            },
            {
              "id": "contact-name-id",
              "name": "contactName",
              "value": "={{ $('Process Text').item.json.contactName }}",
              "type": "string"
            },
            {
              "id": "phone-number-id-field",
              "name": "phoneNumberId",
              "value": "={{ $('Process Text').item.json.phoneNumberId }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        76624,
        22624
      ],
      "id": "50759b34-0b84-47ca-91c6-ed1c9ab712ea",
      "name": "Concat Messages"
    },
    {
      "parameters": {
        "amount": 1.5
      },
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1.1,
      "position": [
        76400,
        22784
      ],
      "id": "46340f7f-4943-4054-a028-e072cc883f38",
      "name": "Wait 5 Seconds",
      "webhookId": "wa-buffer-resume"
    },
    {
      "parameters": {
        "sessionIdType": "customKey",
        "sessionKey": "={{ ($(\"Merge Data\").isExecuted ? $(\"Merge Data\").first().json.phoneNumber : undefined) || ($(\"Merge Audio Data\").isExecuted ? $(\"Merge Audio Data\").first().json.phoneNumber : undefined) || ($(\"Merge Image Data\").isExecuted ? $(\"Merge Image Data\").first().json.phoneNumber : undefined) || ($(\"Button to Text\").isExecuted ? $(\"Button to Text\").first().json.phoneNumber : undefined) || 'default' }}",
        "contextWindowLength": 30
      },
      "type": "@n8n/n8n-nodes-langchain.memoryRedisChat",
      "typeVersion": 1.5,
      "position": [
        77232,
        23184
      ],
      "id": "6a429f90-26fb-4a73-a047-621c2f2afb0c",
      "name": "Redis Chat Memory",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "// Extraer la fecha del buttonId (day_2025-12-17)\nconst input = $input.first().json;\nconst buttonId = input.buttonId;\nconst selectedDay = buttonId.replace('day_', '');\n\nreturn {\n  json: {\n    phoneNumber: input.phoneNumber,\n    phoneNumberId: input.phoneNumberId,\n    contactName: input.contactName,\n    selectedDay: selectedDay\n  }\n};"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        76912,
        23424
      ],
      "id": "bc6b5bc3-95be-4a48-b667-505e7e49af29",
      "name": "Extract Day"
    },
    {
      "parameters": {
        "operation": "getAll",
        "calendar": {
          "__rl": true,
          "value": "contacto@automatizatech.cl",
          "mode": "list",
          "cachedResultName": "contacto@automatizatech.cl"
        },
        "options": {
          "timeMin": "={{ DateTime.fromISO($json.selectedDay + 'T09:00:00', { zone: 'America/Santiago' }).toISO() }}",
          "timeMax": "={{ DateTime.fromISO($json.selectedDay + 'T18:00:00', { zone: 'America/Santiago' }).toISO() }}",
          "singleEvents": true
        }
      },
      "type": "n8n-nodes-base.googleCalendar",
      "typeVersion": 1.1,
      "position": [
        77072,
        23456
      ],
      "alwaysOutputData": true,
      "id": "18751774-9f71-4332-9e15-3370de23910f",
      "name": "Get Calendar Events",
      "credentials": {
        "googleCalendarOAuth2Api": {
          "id": "NrhQQuWgel9eWwzp",
          "name": "Google Calendar AutomatizaTech.cl"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "// Obtener eventos del día seleccionado\nconst items = $input.all();\nconst inputData = $('Extract Day').first().json;\nconst selectedDay = inputData.selectedDay;\n\n// Horarios disponibles (9:00 - 17:00, cada hora)\nconst allSlots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];\n\n// Obtener horarios ocupados\nconst busySlots = [];\nfor (const item of items) {\n  if (item.json.start && item.json.start.dateTime) {\n    const startTime = DateTime.fromISO(item.json.start.dateTime).setZone('America/Santiago');\n    busySlots.push(startTime.toFormat('HH:mm'));\n  }\n}\n\n// Filtrar horarios disponibles\nconst availableSlots = allSlots.filter(slot => !busySlots.includes(slot));\n\n// Tomar los primeros 3 disponibles\nconst slotsToShow = availableSlots.slice(0, 3);\n\n// Si no hay horarios disponibles\nif (slotsToShow.length === 0) {\n  return {\n    json: {\n      ...inputData,\n      hasAvailability: false,\n      message: 'Lo siento, no hay horarios disponibles para ese día. Por favor intenta con otro día.'\n    }\n  };\n}\n\n// Formatear para botones\nconst formattedSlots = slotsToShow.map(slot => ({\n  time: slot,\n  display: slot + ' hrs',\n  btnId: `time_${selectedDay}_${slot.replace(':', '')}`\n}));\n\nreturn {\n  json: {\n    ...inputData,\n    hasAvailability: true,\n    availableSlots: formattedSlots\n  }\n};"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        77296,
        23456
      ],
      "id": "26987b31-ab4e-4e78-b49b-603ff75751af",
      "name": "Find Available Slots"
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": ""
          },
          "conditions": [
            {
              "leftValue": "={{ $json.hasAvailability }}",
              "rightValue": true,
              "operator": {
                "type": "boolean",
                "operation": "equals"
              }
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2,
      "position": [
        77520,
        23456
      ],
      "id": "ea90e247-1361-4c74-a456-5137bf9a74b8",
      "name": "Has Slots?"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"⏰ Horarios disponibles para el {{ DateTime.fromISO($json.selectedDay).toFormat('dd/MM/yyyy') }}:\\n\\nSelecciona tu horario preferido:\"\n    },\n    \"action\": {\n      \"buttons\": [\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableSlots[0].btnId }}\",\n            \"title\": \"{{ $json.availableSlots[0].display }}\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableSlots[1] ? $json.availableSlots[1].btnId : $json.availableSlots[0].btnId }}\",\n            \"title\": \"{{ $json.availableSlots[1] ? $json.availableSlots[1].display : '---' }}\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $json.availableSlots[2] ? $json.availableSlots[2].btnId : $json.availableSlots[0].btnId }}\",\n            \"title\": \"{{ $json.availableSlots[2] ? $json.availableSlots[2].display : '---' }}\"\n          }\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77744,
        23408
      ],
      "id": "4f4c3070-af12-4c4a-95c0-dab4b415d5d8",
      "name": "Send Times Buttons",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"body\": \"😕 {{ $json.message }}\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77744,
        23552
      ],
      "id": "8b9c1a1c-fe69-47d2-8667-14226d40f24b",
      "name": "Send No Slots",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "// Extraer fecha y hora del buttonId (time_2025-12-17_1400)\nconst input = $input.first().json;\nconst buttonId = input.buttonId;\nconst parts = buttonId.replace('time_', '').split('_');\nconst selectedDay = parts[0];\nconst timeRaw = parts[1];\nconst selectedTime = timeRaw.substring(0, 2) + ':' + timeRaw.substring(2);\n\nreturn {\n  json: {\n    phoneNumber: input.phoneNumber,\n    phoneNumberId: input.phoneNumberId,\n    contactName: input.contactName,\n    selectedDay: selectedDay,\n    selectedTime: selectedTime\n  }\n};"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        76896,
        23600
      ],
      "id": "6b6e4791-363b-4b7c-8c42-70120519e89b",
      "name": "Extract Time"
    },
    {
      "parameters": {
        "operation": "set",
        "key": "=booking_{{ $json.phoneNumber }}",
        "value": "={{ JSON.stringify({ day: $json.selectedDay, time: $json.selectedTime, name: '', phone: $json.phoneNumber, phoneNumberId: $json.phoneNumberId, step: 'WAITING_NAME' }) }}",
        "expire": true,
        "ttl": 3600
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        77072,
        23664
      ],
      "id": "c97e366b-b46e-4d73-bdec-07f99bfeb8d6",
      "name": "Save Booking State",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phoneNumber }}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"body\": \"✨ ¡Excelente elección!\\n\\nHas seleccionado:\\n📅 {{ DateTime.fromISO($json.selectedDay).toFormat('dd/MM/yyyy') }}\\n⏰ {{ $json.selectedTime }} hrs\\n\\n👤 Por favor, escribe tu nombre completo para la reserva:\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        77296,
        23664
      ],
      "id": "21e76174-6137-48c7-8e65-0a3657b2f4aa",
      "name": "Ask For Name",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "operation": "get",
        "propertyName": "bookingData",
        "key": "=booking_{{ $('Process Text').first().json.phoneNumber }}",
        "options": {}
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        75728,
        22464
      ],
      "id": "6ebc1568-c753-48c7-8713-a612a59f13cf",
      "name": "Check Pending Booking",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "const input = $input.first().json;\nconst textContent = $('Process Text').first().json.textContent;\n\n// bookingData puede ser un string JSON o null si no existe la key\nlet bookingData = null;\nif (input.bookingData && input.bookingData !== null) {\n  try {\n    bookingData = JSON.parse(input.bookingData);\n  } catch (e) {\n    bookingData = null;\n  }\n}\n\n// Verificar si es un email válido\nconst emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;\nconst isEmail = emailRegex.test(textContent.trim());\n\nif (!bookingData) {\n  // No hay reserva pendiente → flujo normal\n  return {\n    json: {\n      status: 'NORMAL',\n      hasBooking: false,\n      textContent: textContent,\n      phoneNumber: $('Process Text').first().json.phoneNumber,\n      contactName: $('Process Text').first().json.contactName,\n      phoneNumberId: $('Process Text').first().json.phoneNumberId\n    }\n  };\n}\n\n// Hay booking pendiente - verificar en qué paso está\nif (bookingData.step === 'WAITING_NAME') {\n  // Esperando nombre - guardar nombre y pedir email\n  return {\n    json: {\n      status: 'SAVE_NAME',\n      name: textContent.trim(),\n      day: bookingData.day,\n      time: bookingData.time,\n      phone: bookingData.phone,\n      phoneNumber: bookingData.phone,\n      phoneNumberId: bookingData.phoneNumberId\n    }\n  };\n} else if (bookingData.step === 'WAITING_EMAIL') {\n  if (isEmail) {\n    // Tiene nombre y ahora envió email → completar reserva\n    return {\n      json: {\n        status: 'COMPLETE_BOOKING',\n        hasBooking: true,\n        email: textContent.trim(),\n        date: bookingData.day,\n        time: bookingData.time,\n        name: bookingData.name,\n        phone: bookingData.phone,\n        phoneNumber: bookingData.phone,\n        phoneNumberId: bookingData.phoneNumberId\n      }\n    };\n  } else {\n    // Esperando email pero no es email válido\n    return {\n      json: {\n        status: 'ASK_EMAIL',\n        hasPendingBooking: true,\n        textContent: textContent,\n        day: bookingData.day,\n        time: bookingData.time,\n        name: bookingData.name,\n        phone: bookingData.phone,\n        phoneNumber: bookingData.phone,\n        phoneNumberId: bookingData.phoneNumberId\n      }\n    };\n  }\n} else {\n  // Estado desconocido - flujo normal\n  return {\n    json: {\n      status: 'NORMAL',\n      hasBooking: false,\n      textContent: textContent,\n      phoneNumber: $('Process Text').first().json.phoneNumber,\n      contactName: $('Process Text').first().json.contactName,\n      phoneNumberId: $('Process Text').first().json.phoneNumberId\n    }\n  };\n}"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        75952,
        22464
      ],
      "id": "34dd9cee-803a-4c59-b76a-35099a0a9955",
      "name": "Check Email Booking"
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.status }}",
                    "rightValue": "COMPLETE_BOOKING",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "COMPLETE"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.status }}",
                    "rightValue": "SAVE_NAME",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "SAVE_NAME"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.status }}",
                    "rightValue": "ASK_EMAIL",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "ASK_EMAIL"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "loose",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.status }}",
                    "rightValue": "NORMAL",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "NORMAL"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        76176,
        22464
      ],
      "id": "398b4bcc-4318-4d61-8d20-189b2ec1709f",
      "name": "Booking Status"
    },
    {
      "parameters": {
        "workflowId": "PejBgr2LZQSAbbBy",
        "options": {}
      },
      "type": "n8n-nodes-base.executeWorkflow",
      "typeVersion": 1,
      "position": [
        76400,
        22384
      ],
      "id": "44f7631a-4037-4002-a389-6da60241bcdb",
      "name": "Create Appointment"
    },
    {
      "parameters": {
        "operation": "delete",
        "key": "=booking_{{ $('Check Email Booking').first().json.phone }}"
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        76624,
        22384
      ],
      "id": "004f4539-6cb2-435b-a1a3-de7a7e211060",
      "name": "Delete Booking State",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $('Check Email Booking').first().json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $('Check Email Booking').first().json.phone }}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"body\": \"{{ $('Create Appointment').first().json.result || 'Tu cita ha sido agendada exitosamente. Revisa tu correo para más detalles.' }}\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76848,
        22384
      ],
      "id": "1b390314-57a4-44db-bf56-01f68e7b5876",
      "name": "Send Booking Confirmation",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phone }}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"body\": \"¡Ya casi terminamos {{ $json.name }}! 📧\\n\\nTienes una reserva pendiente para el {{ $json.day.replace(/-/g, '/') }} a las {{ $json.time }}.\\n\\nPor favor, envíame tu correo electrónico para confirmar la cita y recibir la invitación con el enlace de Google Meet.\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76400,
        22624
      ],
      "id": "91edc938-557b-4480-a0bd-ff5248d57ede",
      "name": "Remind Email",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    },
    {
      "parameters": {
        "operation": "set",
        "key": "=booking_{{ $json.phone }}",
        "value": "={{ JSON.stringify({ day: $json.day, time: $json.time, name: $json.name, phone: $json.phone, phoneNumberId: $json.phoneNumberId, step: 'WAITING_EMAIL' }) }}",
        "expire": true,
        "ttl": 3600
      },
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        76400,
        22512
      ],
      "id": "a1bc63ed-9df6-4918-bb60-d569e3e3b026",
      "name": "Save Name",
      "credentials": {
        "redis": {
          "id": "fgxjc2NeBOcUCA3v",
          "name": "Redis32"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
        "authentication": "predefinedCredentialType",
        "nodeCredentialType": "whatsAppApi",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"recipient_type\": \"individual\",\n  \"to\": \"{{ $json.phone }}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"body\": \"¡Perfecto {{ $json.name }}! 📧\\n\\nAhora por favor escribe tu correo electrónico para enviarte la invitación con el enlace de Google Meet:\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [
        76624,
        22512
      ],
      "id": "a844a274-aaed-4523-ae80-b779d9616165",
      "name": "Ask For Email",
      "credentials": {
        "whatsAppApi": {
          "id": "TVTLZP26kDJjR0KP",
          "name": "WhatsApp account"
        }
      }
    }
  ],
  "pinData": {},
  "connections": {
    "Verify Webhook (GET)": {
      "main": [
        [
          {
            "node": "Respond Challenge",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "WhatsApp Webhook": {
      "main": [
        [
          {
            "node": "Has Message?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Has Message?": {
      "main": [
        [
          {
            "node": "Extract Message Data",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract Message Data": {
      "main": [
        [
          {
            "node": "Deduplication",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Deduplication": {
      "main": [
        [
          {
            "node": "Message Type",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Message Type": {
      "main": [
        [
          {
            "node": "Process Text",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Process Audio",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Process Image",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Process Interactive",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Process Text": {
      "main": [
        [
          {
            "node": "Check Pending Booking",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Check Pending Booking": {
      "main": [
        [
          {
            "node": "Check Email Booking",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Check Email Booking": {
      "main": [
        [
          {
            "node": "Booking Status",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Booking Status": {
      "main": [
        [
          {
            "node": "Create Appointment",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Save Name",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Remind Email",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Redis Push",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Save Name": {
      "main": [
        [
          {
            "node": "Ask For Email",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Create Appointment": {
      "main": [
        [
          {
            "node": "Delete Booking State",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Delete Booking State": {
      "main": [
        [
          {
            "node": "Send Booking Confirmation",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis Push": {
      "main": [
        [
          {
            "node": "Redis Get",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis Get": {
      "main": [
        [
          {
            "node": "Check Message Status",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Check Message Status": {
      "main": [
        [],
        [
          {
            "node": "Redis Delete",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Wait 5 Seconds",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis Delete": {
      "main": [
        [
          {
            "node": "Concat Messages",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Concat Messages": {
      "main": [
        [
          {
            "node": "Get Exchange Rate",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Exchange Rate": {
      "main": [
        [
          {
            "node": "Merge Data",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Merge Data": {
      "main": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Process Audio": {
      "main": [
        [
          {
            "node": "Get Audio URL",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Audio URL": {
      "main": [
        [
          {
            "node": "Download Audio",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Download Audio": {
      "main": [
        [
          {
            "node": "Transcribe Audio (Whisper)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Transcribe Audio (Whisper)": {
      "main": [
        [
          {
            "node": "Audio to Text",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Audio to Text": {
      "main": [
        [
          {
            "node": "Get Exchange Rate Audio",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Exchange Rate Audio": {
      "main": [
        [
          {
            "node": "Merge Audio Data",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Merge Audio Data": {
      "main": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Process Image": {
      "main": [
        [
          {
            "node": "Get Image URL",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Image URL": {
      "main": [
        [
          {
            "node": "Download Image",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Download Image": {
      "main": [
        [
          {
            "node": "Prepare Base64 Image",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Prepare Base64 Image": {
      "main": [
        [
          {
            "node": "Analyze Image (Vision)",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Analyze Image (Vision)": {
      "main": [
        [
          {
            "node": "Image to Text",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Image to Text": {
      "main": [
        [
          {
            "node": "Get Exchange Rate Image",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Exchange Rate Image": {
      "main": [
        [
          {
            "node": "Merge Image Data",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Merge Image Data": {
      "main": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Process Interactive": {
      "main": [
        [
          {
            "node": "Button Action",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Button Action": {
      "main": [
        [
          {
            "node": "Button to Text",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Button to Text",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Button to Text",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Extract Day",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Extract Time",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Button to Text",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Button to Text": {
      "main": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Agente IA - Tech WhatsApp": {
      "main": [
        [
          {
            "node": "Parse Response Actions",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Parse Response Actions": {
      "main": [
        [
          {
            "node": "Route Action",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Route Action": {
      "main": [
        [
          {
            "node": "Send WhatsApp Response",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Generate Days",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Send Cancel Buttons",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Generate Days",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Send Support Escalation",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Send Plans Buttons",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Cerebro GPT-4o": {
      "ai_languageModel": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "Wait 5 Seconds": {
      "main": [
        [
          {
            "node": "Redis Get",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis Chat Memory": {
      "ai_memory": [
        [
          {
            "node": "Agente IA - Tech WhatsApp",
            "type": "ai_memory",
            "index": 0
          }
        ]
      ]
    },
    "Generate Days": {
      "main": [
        [
          {
            "node": "Send Calendar Buttons",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract Day": {
      "main": [
        [
          {
            "node": "Get Calendar Events",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Calendar Events": {
      "main": [
        [
          {
            "node": "Find Available Slots",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Find Available Slots": {
      "main": [
        [
          {
            "node": "Has Slots?",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Has Slots?": {
      "main": [
        [
          {
            "node": "Send Times Buttons",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Send No Slots",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract Time": {
      "main": [
        [
          {
            "node": "Save Booking State",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Save Booking State": {
      "main": [
        [
          {
            "node": "Ask For Name",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "active": true,
  "settings": {
    "executionOrder": "v1",
    "timeSavedMode": "fixed",
    "timezone": "America/Santiago",
    "callerPolicy": "workflowsFromSameOwner",
    "executionTimeout": -1,
    "availableInMCP": false
  },
  "versionId": "3c9f2842-041a-4881-92ac-1f198c1a5a40",
  "meta": {
    "templateCredsSetupCompleted": true,
    "instanceId": "fefc56565e860fc896f35943e8154e1638134eaf8454ea973f35266ab1b53147"
  },
  "id": "bBcNlFgBzQ0766Mq",
  "tags": []
}# 📱 Documentación: Chatbot WhatsApp con N8N

## Guía Completa de Implementación para Clientes

**Versión:** 1.0  
**Fecha:** Diciembre 2025  
**Proyecto Base:** AutomatizaTech

---

## 📋 Índice

1. [Requisitos Previos](#1-requisitos-previos)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Configuración de Servicios](#3-configuración-de-servicios)
4. [Estructura del Workflow](#4-estructura-del-workflow)
5. [Flujos Principales](#5-flujos-principales)
6. [Validaciones Implementadas](#6-validaciones-implementadas)
7. [Personalización para Clientes](#7-personalización-para-clientes)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Requisitos Previos

### 1.1 Infraestructura
- **VPS/Servidor**: Ubuntu 20.04+ (Hostinger VPS recomendado)
- **Panel de gestión**: Easypanel (facilita despliegue de contenedores)
- **Dominio**: Con certificado SSL configurado

### 1.2 Servicios Necesarios
| Servicio | Propósito | Proveedor |
|----------|-----------|-----------|
| N8N | Motor de automatización | Self-hosted via Easypanel |
| Redis | Cache y estados temporales | Self-hosted via Easypanel |
| WhatsApp Business API | Canal de comunicación | Meta Cloud API |
| OpenAI API | IA conversacional y visión | OpenAI |
| Google Calendar | Gestión de citas | Google Cloud |
| WordPress | Base de datos de citas | Self-hosted |

### 1.3 Credenciales Requeridas
```
□ WhatsApp Business API Token
□ OpenAI API Key
□ Google Calendar OAuth2
□ Redis Connection String
□ WordPress API Endpoint
```

---

## 2. Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        USUARIO WHATSAPP                          │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     META CLOUD API (v22.0)                       │
│                   WhatsApp Business Platform                     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                         N8N WORKFLOW                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │ Webhook  │→ │Dedup/    │→ │ Message  │→ │   AI     │        │
│  │ Receiver │  │ Buffer   │  │  Router  │  │  Agent   │        │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘        │
│                      │                           │               │
│                      ▼                           ▼               │
│               ┌──────────┐               ┌──────────┐           │
│               │  REDIS   │               │  OpenAI  │           │
│               │  Cache   │               │  GPT-4o  │           │
│               └──────────┘               └──────────┘           │
└─────────────────────────────────────────────────────────────────┘
                                │
                    ┌───────────┴───────────┐
                    ▼                       ▼
        ┌──────────────────┐    ┌──────────────────┐
        │  Google Calendar │    │    WordPress     │
        │   (Eventos)      │    │   (Base Datos)   │
        └──────────────────┘    └──────────────────┘
```

---

## 3. Configuración de Servicios

### 3.1 WhatsApp Business API (Meta)

#### Paso 1: Crear App en Meta Developers
1. Ir a [developers.facebook.com](https://developers.facebook.com)
2. Crear nueva App → Tipo: "Business"
3. Agregar producto "WhatsApp"

#### Paso 2: Configurar Webhook
```
URL del Webhook: https://tu-dominio.com/webhook/whatsapp-webhook-tech
Verificación Token: [generar token seguro]
Suscripciones: messages, message_deliveries, message_reads
```

#### Paso 3: Obtener Credenciales
```json
{
  "phone_number_id": "XXXXXXXXXXXXXXX",
  "access_token": "EAAG...",
  "business_account_id": "XXXXXXXXXXXXXXX"
}
```

### 3.2 Redis (via Easypanel)

#### Configuración en Easypanel
1. Crear nuevo servicio → Redis
2. Configurar:
   - **Nombre**: n8n_redis32 (o similar)
   - **Puerto**: 6379
   - **Password**: [generar password seguro]

#### Connection String para N8N
```
redis://:[password]@n8n_redis32:6379
```

### 3.3 OpenAI API

#### Obtener API Key
1. Ir a [platform.openai.com](https://platform.openai.com)
2. API Keys → Create new secret key
3. Guardar la key de forma segura

#### Modelos Utilizados
| Modelo | Uso |
|--------|-----|
| gpt-4o | Chat conversacional + Visión |
| whisper-1 | Transcripción de audio |

### 3.4 Google Calendar

#### Paso 1: Crear Proyecto en Google Cloud
1. Ir a [console.cloud.google.com](https://console.cloud.google.com)
2. Crear nuevo proyecto
3. Habilitar "Google Calendar API"

#### Paso 2: Configurar OAuth2
1. Crear credenciales OAuth 2.0
2. Tipo: Web application
3. Redirect URI: `https://tu-n8n.com/rest/oauth2-credential/callback`

#### Paso 3: Conectar en N8N
1. Credentials → Google Calendar OAuth2
2. Autorizar con cuenta del cliente

---

## 4. Estructura del Workflow

### 4.1 Nodos Principales

```
WhatsApp_Tech_Principal.json
├── ENTRADA
│   ├── WhatsApp Webhook (POST) - Recibe mensajes
│   └── Verify Webhook (GET) - Verificación Meta
│
├── PRE-PROCESAMIENTO
│   ├── Has Message? - Filtra mensajes válidos
│   ├── Extract Message Data - Extrae datos básicos
│   ├── Deduplication - Evita duplicados (staticData)
│   └── Message Type (Switch) - Rutea por tipo
│
├── PROCESAMIENTO POR TIPO
│   ├── TEXTO
│   │   ├── Process Text
│   │   ├── Check Pending Booking - Verifica reservas pendientes
│   │   ├── Booking Status (Switch) - Rutea estado de reserva
│   │   ├── Redis Push/Get - Buffer de mensajes
│   │   ├── Check Message Status - Espera concatenación
│   │   └── Concat Messages - Une mensajes
│   │
│   ├── AUDIO
│   │   ├── Process Audio
│   │   ├── Get Audio URL
│   │   ├── Download Audio
│   │   ├── Transcribe Audio (Whisper)
│   │   └── Audio to Text
│   │
│   ├── IMAGEN
│   │   ├── Process Image
│   │   ├── Get Image URL
│   │   ├── Download Image
│   │   ├── Prepare Base64 Image
│   │   ├── Analyze Image (Vision)
│   │   └── Image to Text
│   │
│   └── INTERACTIVE (Botones)
│       ├── Process Interactive
│       ├── Button Action (Switch) - Rutea por botón
│       └── Button to Text
│
├── INTELIGENCIA ARTIFICIAL
│   ├── Get Exchange Rate - Obtiene tipo de cambio
│   ├── Merge Data - Prepara datos para AI
│   ├── Agente IA - Tech WhatsApp - Cerebro principal
│   ├── Cerebro GPT-4o - Modelo de lenguaje
│   └── Redis Chat Memory - Memoria conversacional
│
├── POST-PROCESAMIENTO
│   ├── Parse Response Actions - Detecta acciones especiales
│   └── Route Action (Switch) - Rutea por acción
│
├── RESPUESTAS
│   ├── Send WhatsApp Response - Texto simple
│   ├── Send Calendar Buttons - Botones de días
│   ├── Send Times Buttons - Botones de horas
│   ├── Send Cancel Buttons - Confirmar cancelación
│   ├── Send Plans Buttons - Mostrar planes
│   └── Send Support Escalation - Escalar soporte
│
└── CALENDARIO
    ├── Generate Days - Genera días hábiles
    ├── Extract Day - Extrae día seleccionado
    ├── Get Calendar Events - Consulta eventos
    ├── Find Available Slots - Encuentra horas libres
    ├── Has Slots? - Verifica disponibilidad
    ├── Extract Time - Extrae hora seleccionada
    ├── Save Booking State - Guarda estado en Redis
    ├── Ask For Name - Solicita nombre
    ├── Ask Valid Name - Solicita nombre válido
    ├── Save Name - Guarda nombre
    ├── Ask For Email - Solicita email
    ├── Remind Email - Recuerda email inválido
    ├── Create Appointment - Crea cita (subworkflow)
    ├── Delete Booking State - Limpia estado
    └── Send Booking Confirmation - Confirma reserva
```

### 4.2 Credenciales Configuradas en N8N

| Nombre | Tipo | ID | Uso |
|--------|------|-----|-----|
| WhatsApp account | WhatsApp Business Cloud | TVTLZP26kDJjR0KP | Envío/recepción mensajes |
| OpenAi account | OpenAI | g52IEXpRfN5r7jKw | GPT-4o y Whisper |
| Redis32 | Redis | fgxjc2NeBOcUCA3v | Cache y estados |
| Google Calendar | Google Calendar OAuth2 | NrhQQuWgel9eWwzp | Gestión de citas |

---

## 5. Flujos Principales

### 5.1 Flujo de Mensaje de Texto

```
Usuario envía texto
        │
        ▼
┌───────────────────┐
│ Check Pending     │ ← Verifica si hay reserva pendiente
│ Booking (Redis)   │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Booking Status    │
│ (Switch)          │
└───────────────────┘
        │
   ┌────┴────┬────────┬────────┬────────┐
   ▼         ▼        ▼        ▼        ▼
COMPLETE  SAVE_NAME  INVALID  INVALID  NORMAL
   │         │       EMAIL    NAME       │
   │         │        │        │         │
   ▼         ▼        ▼        ▼         ▼
Crear    Guardar   Pedir    Pedir    Buffer
Cita     Nombre    Email    Nombre   Redis
           │       Válido   Válido     │
           ▼                           ▼
        Pedir                      Concatenar
        Email                      Mensajes
                                      │
                                      ▼
                                   Agente IA
```

### 5.2 Flujo de Audio (Nota de Voz)

```
Usuario envía audio
        │
        ▼
┌───────────────────┐
│ Get Audio URL     │ ← Obtiene URL del archivo
│ (Meta Graph API)  │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Download Audio    │ ← Descarga archivo binario
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Transcribe Audio  │ ← Whisper API
│ (OpenAI)          │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Audio to Text     │ ← Prepara para AI
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Agente IA         │
└───────────────────┘
```

### 5.3 Flujo de Imagen

```
Usuario envía imagen
        │
        ▼
┌───────────────────┐
│ Get Image URL     │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Download Image    │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Prepare Base64    │ ← Convierte a base64
└───────────────────┘
        │
        ▼
┌───────────────────────────────────┐
│ Analyze Image (Vision)            │
│ HTTP Request → OpenAI GPT-4o      │
│                                   │
│ Prompt: "Describe EXACTAMENTE lo  │
│ que ves en esta imagen. LEE Y     │
│ TRANSCRIBE TODO EL TEXTO VISIBLE" │
└───────────────────────────────────┘
        │
        ▼
┌───────────────────┐
│ Agente IA         │ ← Recibe descripción de imagen
└───────────────────┘
```

### 5.4 Flujo de Botones Interactivos

```
Usuario presiona botón
        │
        ▼
┌───────────────────┐
│ Process           │
│ Interactive       │
└───────────────────┘
        │
        ▼
┌───────────────────┐
│ Button Action     │
│ (Switch v3)       │
└───────────────────┘
        │
   ┌────┴────┬────────┬────────┬────────┬────────┐
   ▼         ▼        ▼        ▼        ▼        ▼
Agendar   Ver      Soporte   Día      Hora    Fallback
Demo      Planes            Selec.   Selec.     │
   │         │        │        │        │        │
   ▼         ▼        ▼        ▼        ▼        ▼
Button   Button   Button   Extract  Extract  Button
to Text  to Text  to Text    Day      Time   to Text
   │                          │        │
   │                          ▼        ▼
   │                       Calendar  Save
   │                        Events   Booking
   │                          │        │
   │                          ▼        ▼
   │                       Send      Ask
   │                       Times     Name
   │                       Buttons
   │
   └──────────────────────────────────────────────┐
                                                  ▼
                                            Agente IA
```

### 5.5 Flujo Completo de Agendamiento

```
1. Usuario: "quiero agendar una demo"
        │
        ▼
2. AI detecta intención → <<ACTION:SHOW_CALENDAR>>
        │
        ▼
3. Route Action → Generate Days
        │
        ▼
4. Envía botones con 3 días hábiles:
   ┌─────────────────────────────┐
   │ 📅 Selecciona el día:       │
   │ [Lun 16/12] [Mar 17/12]    │
   │ [Mié 18/12]                 │
   └─────────────────────────────┘
        │
        ▼
5. Usuario presiona día → Button Action → Extract Day
        │
        ▼
6. Get Calendar Events → Find Available Slots
        │
        ▼
7. Envía botones de horarios:
   ┌─────────────────────────────┐
   │ ⏰ Horarios disponibles:     │
   │ [09:00 hrs] [10:00 hrs]    │
   │ [14:00 hrs]                 │
   └─────────────────────────────┘
        │
        ▼
8. Usuario presiona hora → Extract Time → Save Booking State
        │
        ▼
9. Ask For Name:
   "✨ Has seleccionado: 17/12/2025 10:00 hrs
    👤 Por favor, escribe tu nombre completo:"
        │
        ▼
10. Usuario envía nombre → Validación
    ├── Válido (≥2 chars, solo letras) → Save Name → Ask For Email
    └── Inválido → Ask Valid Name (pedir de nuevo)
        │
        ▼
11. Ask For Email:
    "¡Perfecto Juan! 📧
     Escribe tu correo electrónico:"
        │
        ▼
12. Usuario envía email → Validación
    ├── Válido (regex) → Create Appointment → Confirmación
    └── Inválido → Remind Email (pedir de nuevo)
        │
        ▼
13. Subworkflow crea evento en Google Calendar + guarda en WordPress
        │
        ▼
14. Send Booking Confirmation:
    "✅ Tu cita ha sido agendada exitosamente.
     Revisa tu correo para el enlace de Google Meet."
```

---

## 6. Validaciones Implementadas

### 6.1 Validación de Nombre

```javascript
// Código en Check Email Booking
const nameText = textContent.trim();
const isValidName = nameText.length >= 2 && 
  /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/.test(nameText);
```

**Reglas:**
- ✅ Mínimo 2 caracteres
- ✅ Solo letras (incluyendo acentos españoles)
- ✅ Permite espacios (para nombres compuestos)
- ❌ Rechaza números
- ❌ Rechaza caracteres especiales

**Mensaje de error:**
```
😅 Parece que el nombre ingresado no es válido.

Por favor, escribe tu nombre completo (mínimo 2 caracteres, solo letras):
```

### 6.2 Validación de Email

```javascript
// Código en Check Email Booking
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isEmail = emailRegex.test(textContent.trim());
```

**Reglas:**
- ✅ Formato: `usuario@dominio.extensión`
- ✅ No permite espacios
- ✅ Requiere @ y punto

**Mensaje de error:**
```
😅 [Nombre], parece que el correo ingresado no es válido.

Por favor, escribe un correo electrónico válido 
(ejemplo: tucorreo@gmail.com) para enviarte la invitación 
con el enlace de Google Meet:
```

### 6.3 Deduplicación de Mensajes

```javascript
// Código en Deduplication
const messageId = $input.first().json.messageId;
const staticData = $getWorkflowStaticData('global');

if (!staticData.processedIds) {
  staticData.processedIds = [];
}

if (staticData.processedIds.includes(messageId)) {
  return []; // Stop execution - mensaje ya procesado
}

staticData.processedIds.push(messageId);

// Mantener lista manejable (últimos 1000)
if (staticData.processedIds.length > 1000) {
  staticData.processedIds.shift();
}
```

### 6.4 Concatenación de Mensajes (Buffer Redis)

```javascript
// Flujo para agrupar mensajes rápidos
1. Redis Push → Guarda mensaje con timestamp
2. Redis Get → Lee todos los mensajes del usuario
3. Check Message Status → Verifica si es el último mensaje
   - Si sessionID no coincide → Ignorar (llegó otro mensaje)
   - Si timestamp > 7 segundos → Procesar
   - Si timestamp < 7 segundos → Esperar
4. Wait 1.5 Seconds → Da tiempo para más mensajes
5. Concat Messages → Une todos los mensajes en uno
```

---

## 7. Personalización para Clientes

### 7.1 Variables a Personalizar

```javascript
// En el System Prompt del Agente IA
const CONFIG = {
  nombreBot: "Tech",              // Cambiar por nombre del cliente
  nombreEmpresa: "AutomatizaTech", // Cambiar por empresa
  
  // Contacto
  web: "https://www.automatizatech.cl",
  whatsapp: "+56 9 4033 1127",
  email: "contacto@automatizatech.cl",
  instagram: "@automatizaTech.cl",
  
  // Planes (ajustar según cliente)
  planes: {
    basico: { precio: 99, conversaciones: 1000 },
    profesional: { precio: 199, conversaciones: 5000 },
    enterprise: { precio: 399, conversaciones: "Ilimitado" }
  },
  
  // Calendario
  calendarioEmail: "contacto@automatizatech.cl",
  horariosDisponibles: ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'],
  diasAnticipacion: 3, // Días hábiles a mostrar
  
  // Zona horaria
  timezone: "America/Santiago"
};
```

### 7.2 System Prompt Template

```
Eres [NOMBRE_BOT], el asistente virtual experto de [EMPRESA] en WhatsApp. 
Tu misión es [MISIÓN_ESPECÍFICA].

TU IDENTIDAD Y PERSONALIDAD
- Nombre: [NOMBRE_BOT]
- Empresa: [EMPRESA]
- Canal: WhatsApp Business
- Tono: [TONO - ej: Profesional, cercano y amable]
- Estilo: [ESTILO - ej: Conciso, resolutivo]

SERVICIOS Y PLANES
[DESCRIBIR PLANES DEL CLIENTE]

REGLAS DE PRECIOS
1. Tipo de Cambio Actual: ${{ $json.rate }} CLP por USD
2. Formato: $PRECIO_USD USD (aprox. $PRECIO_CLP CLP)

⚠️ ACCIONES ESPECIALES - OBLIGATORIO ⚠️
ES OBLIGATORIO incluir UNA etiqueta de acción cuando el usuario:
- Quiere AGENDAR demo/reunión → <<ACTION:SHOW_CALENDAR>>
- Quiere CANCELAR cita → <<ACTION:CANCEL_APPOINTMENT>>
- Quiere REPROGRAMAR cita → <<ACTION:RESCHEDULE_APPOINTMENT>>
- Necesita SOPORTE técnico → <<ACTION:ESCALATE_SUPPORT>>
- Quiere ver PLANES/precios → <<ACTION:SHOW_PLANS>>

CONTACTO
- Web: [URL]
- WhatsApp: [NUMERO]
- Email: [EMAIL]
```

### 7.3 Checklist de Implementación para Cliente

```
□ 1. INFRAESTRUCTURA
  □ Servidor configurado con Easypanel
  □ N8N instalado y funcionando
  □ Redis instalado y conectado
  □ SSL configurado en dominio

□ 2. WHATSAPP BUSINESS
  □ Meta Developer App creada
  □ WhatsApp Business API configurado
  □ Webhook verificado
  □ Número de teléfono aprobado
  □ Plantillas de mensaje aprobadas (si aplica)

□ 3. CREDENCIALES N8N
  □ WhatsApp Business Cloud API
  □ OpenAI API
  □ Redis
  □ Google Calendar OAuth2 (si usa calendario)

□ 4. PERSONALIZACIÓN
  □ System prompt adaptado
  □ Planes y precios actualizados
  □ Información de contacto actualizada
  □ Horarios de atención configurados
  □ Zona horaria correcta

□ 5. SUBWORKFLOWS
  □ Tech_Calendar_Subworkflow importado (si usa calendario)
  □ ID del subworkflow actualizado en Create Appointment

□ 6. PRUEBAS
  □ Mensaje de texto funciona
  □ Nota de voz se transcribe
  □ Imagen se analiza correctamente
  □ Botones interactivos funcionan
  □ Agendamiento completo funciona
  □ Validaciones de nombre y email funcionan

□ 7. PRODUCCIÓN
  □ Workflow activado
  □ Monitoreo configurado
  □ Backup del workflow guardado
```

---

## 8. Troubleshooting

### 8.1 Problemas Comunes

#### "Los botones no aparecen"
**Causa:** El AI Agent no está incluyendo las etiquetas `<<ACTION:...>>`  
**Solución:** Verificar el System Prompt y agregar ejemplos más explícitos

#### "El mensaje se procesa dos veces"
**Causa:** Deduplicación no funciona  
**Solución:** Verificar que el nodo Deduplication esté conectado correctamente

#### "No se conecta al calendario"
**Causa:** OAuth2 expirado o mal configurado  
**Solución:** Re-autorizar credenciales de Google Calendar en N8N

#### "Audio no se transcribe"
**Causa:** Formato de audio no soportado o API key inválida  
**Solución:** Verificar credenciales OpenAI y formato del audio (debe ser OGG/Opus)

#### "Switch no rutea correctamente"
**Causa:** Falta `mode: rules` en el Switch v3  
**Solución:** Agregar en parameters: `"mode": "rules"`

```json
{
  "parameters": {
    "mode": "rules",  // ← OBLIGATORIO para Switch v3
    "rules": { ... }
  }
}
```

### 8.2 Logs y Depuración

#### Ver ejecuciones en N8N
```
N8N → Executions → Filtrar por workflow
```

#### Verificar Redis
```bash
redis-cli -h n8n_redis32 -a [password]
KEYS *
GET booking_56912345678
```

#### Verificar Webhook
```bash
# En el servidor
curl -X POST https://tu-dominio.com/webhook/whatsapp-webhook-tech \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

### 8.3 Estructura de Datos

#### Mensaje entrante de WhatsApp
```json
{
  "body": {
    "entry": [{
      "changes": [{
        "value": {
          "messages": [{
            "id": "wamid.XXX",
            "from": "56912345678",
            "timestamp": "1702745600",
            "type": "text|audio|image|interactive",
            "text": { "body": "Hola" },
            "audio": { "id": "XXX" },
            "image": { "id": "XXX", "caption": "..." },
            "interactive": {
              "type": "button_reply",
              "button_reply": { "id": "btn_xxx", "title": "..." }
            }
          }],
          "contacts": [{
            "profile": { "name": "Juan" }
          }],
          "metadata": {
            "phone_number_id": "XXXXX"
          }
        }
      }]
    }]
  }
}
```

#### Estado de Booking en Redis
```json
{
  "day": "2025-12-17",
  "time": "10:00",
  "name": "Juan Pérez",
  "phone": "56912345678",
  "phoneNumberId": "XXXXX",
  "step": "WAITING_NAME|WAITING_EMAIL"
}
```

---

## 📎 Archivos Relacionados

| Archivo | Descripción |
|---------|-------------|
| `WhatsApp_Tech_Principal.json` | Workflow principal |
| `Tech_Calendar_Subworkflow.json` | Subworkflow de calendario |
| `DOCUMENTACION-WHATSAPP-CHATBOT.md` | Esta documentación |

---

## 🔄 Historial de Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | Dic 2025 | Versión inicial con todas las funcionalidades |

---

**Desarrollado por:** AutomatizaTech  
**Contacto:** contacto@automatizatech.cl  
**Web:** https://www.automatizatech.cl
