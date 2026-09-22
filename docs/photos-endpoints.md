# Photos — feed e eliminação

Dois endpoints em `v2/photos` para consumir e gerir o feed transversal de fotos submetidas pelos utilizadores.

- `GET  /v2/photos/latest` — feed público paginado das últimas fotos aprovadas.
- `POST /v2/photos/{photoId}/delete` — eliminação de foto, protegida por `PHOTO_MODERATION_KEY`.

Base URL de produção: `https://api.fogos.pt`

---

## 1. `GET /v2/photos/latest`

Devolve as fotos mais recentes com `status = approved` e `public = true`, ordenadas por data de upload (`created_at desc`), agregadas de todos os incidentes.

### Query params

| Param      | Tipo    | Default | Limites    | Descrição                          |
|------------|---------|---------|------------|------------------------------------|
| `per_page` | integer | `30`    | `1`–`100`  | Fotos por página.                  |
| `page`     | integer | `1`     | `>= 1`     | Nº da página (paginação Laravel).  |

### Cache

`Cache-Control: public, s-maxage=120, max-age=60, stale-while-revalidate=300`

### Exemplo

```bash
curl -s "https://api.fogos.pt/v2/photos/latest?per_page=30&page=1"
```

### Resposta `200`

```json
{
  "success": true,
  "data": [
    {
      "id": "6712aabbccddeeff00112233",
      "fire_id": "2025010012345",
      "url": "https://minio.fogos.pt/incident-photos/incidents/2025010012345/6712aabbccddeeff00112233.jpg",
      "taken_at": "2026-09-22T14:31:07+00:00",
      "captured_at": "2026-09-22T14:31:07+00:00",
      "created_at": "2026-09-22T14:33:19+00:00",
      "width": 2048,
      "height": 1536,
      "gps": {
        "lat": 40.12345,
        "lng": -8.54321,
        "altitude_m": 312.4,
        "heading_deg": 128.5
      }
    }
  ],
  "meta": {
    "total": 842,
    "page": 1,
    "per_page": 30
  }
}
```

### Notas

- `fire_id` é o ID público do incidente — usa-o para linkar de volta a `GET /v2/incidents/{id}/photos` ou `https://fogos.pt/pt/fogo/{fire_id}/detalhe`.
- `gps` pode ser `null` se a foto não trouxer coordenadas EXIF válidas.
- EXIF cru nunca é exposto — só lat/lng/altitude/heading.

---

## 2. `POST /v2/photos/{photoId}/delete`

Remove uma foto de forma permanente. Apaga o objeto no MinIO (best-effort — se falhar, a limpeza da BD prossegue à mesma) e o documento em `incident_photos`.

### Autenticação

Header obrigatório:

```
key: <PHOTO_MODERATION_KEY>
```

Sem o header, ou com valor diferente do `PHOTO_MODERATION_KEY` no `.env`, responde `401`.

### Path params

| Param     | Descrição                            |
|-----------|--------------------------------------|
| `photoId` | `_id` da foto (string, ObjectId hex). |

### Exemplo

```bash
curl -X POST "https://api.fogos.pt/v2/photos/6712aabbccddeeff00112233/delete" \
  -H "key: $PHOTO_MODERATION_KEY"
```

### Respostas

| Status | Body                          | Quando                                      |
|--------|-------------------------------|---------------------------------------------|
| `200`  | `{"success": true}`           | Foto eliminada.                             |
| `401`  | —                             | Header `key` em falta ou incorreto.         |
| `404`  | —                             | Não existe foto com esse `photoId`.         |

### Notas operacionais

- A operação é **irreversível** — não há soft-delete nem lixeira.
- Falhas na remoção do MinIO ficam registadas em log (`photo delete: storage cleanup failed`) mas não bloqueiam o `delete` na BD, para evitar registos órfãos.
- Para rejeitar fotos em fila de moderação (workflow normal), continua a usar-se `POST /v2/moderation/photos/{photoId}/reject`, que faz soft-reject (mantém o registo com `status = rejected`). Este endpoint aqui é a via administrativa para apagar de vez, usando a mesma `PHOTO_MODERATION_KEY`.
