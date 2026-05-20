# fogos-renderer

Headless screenshot microservice for FogosPT social media cards. Replaces the legacy `screenshot-script.js` + `fogos.chrome` CDP setup.

## API

### `POST /render`

Body (JSON):

| field    | type   | required | notes |
|----------|--------|----------|-------|
| `url`    | string | yes      | Full URL to capture |
| `width`  | int    | no       | Viewport width (default `DEFAULT_WIDTH`) |
| `height` | int    | no       | Viewport height (default `DEFAULT_HEIGHT`) |
| `waitFor`| string | no       | CSS selector to wait for before capturing (e.g. `.leaflet-tile-loaded`) |
| `minBytes`| int   | no       | Reject the render if output is smaller than this |

Response: `image/png` body, or `4xx/5xx` JSON `{ error }`.

### `GET /healthz`

Returns `{ ok, pool, waiters }`.

## Config (env)

- `PORT` (default 3000)
- `POOL_SIZE` (default 3) — pre-warmed browser contexts
- `CONTEXT_MAX_USES` (default 50) — recycle context after N renders
- `RENDER_TIMEOUT_MS` (default 15000)
- `DEFAULT_WIDTH` / `DEFAULT_HEIGHT`
- `SETTLE_MS` (default 1500) — extra wait after `networkidle` for map tiles to paint

## Local test

```
docker build -t fogos-renderer .
docker run --rm -p 3000:3000 fogos-renderer
curl -X POST http://127.0.0.1:3000/render \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://fogos.pt/?risk=1","width":1200,"height":1300}' \
  --output /tmp/test.png
```
