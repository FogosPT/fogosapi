# Migração de consumidores IPMA → FogosPT API

Este documento descreve **como um agente autónomo deve migrar um projecto consumidor** que hoje faz pedidos directos a `api.ipma.pt` para os endpoints equivalentes servidos pela FogosPT API (`https://api.fogos.pt`).

O objectivo é reduzir a carga no IPMA e cumprir a política de utilização do serviço, mantendo o formato dos payloads praticamente idêntico ao upstream — a maior parte das migrações resume-se a **substituir a URL e adicionar cabeçalhos de autenticação**.

---

## 1. Pré-requisitos (antes de mexer no código do consumidor)

1. **Obter um token pessoal `X-API-Key`** para a FogosPT API — pedido formal via <https://fogos.pt/pt/api>. O token é individual e não pode ser partilhado entre projectos.
2. **Escolher um `User-Agent` identificável** para o projecto (ex.: `MinhaAppMunicipal/1.4 (+contacto@dominio.pt)`).
3. Se o serviço tiver IPs de saída fixos, tê-los à mão — o `X-Origin-IP` é recomendado sempre que possível.
4. Confirmar em que ambientes o consumidor corre (dev, staging, prod) e usar tokens/User-Agent distintos por ambiente, para poderem ser limitados/bloqueados de forma isolada em caso de problema.

---

## 2. Cabeçalhos obrigatórios em **todos** os pedidos

Conforme <https://fogos.pt/pt/api>:

| Header | Obrigatoriedade | Formato / Valor |
|---|---|---|
| `X-API-Key` | **Obrigatório** | Token individual atribuído ao projecto. |
| `User-Agent` | **Obrigatório** | Identificável e personalizado (nome do projecto, versão, contacto). Não deve ser o default de bibliotecas HTTP (`python-requests/x.y`, `Guzzle/x`, `okhttp/x`, `Java/x`, etc.). |
| `X-Origin-IP` | Recomendado | IP público de origem do consumidor, quando conhecido. |

**Consequência de não cumprir:** pedidos podem ser limitados ou bloqueados sem aviso.

**Como aplicar:** configurar os headers **no cliente HTTP partilhado** (interceptor, middleware, factory) — nunca em cada call site individual. Se o consumidor já tiver um wrapper para chamadas IPMA, é aí que a mudança entra.

Exemplo (Guzzle / PHP):

```php
$client = new \GuzzleHttp\Client([
    'base_uri' => 'https://api.fogos.pt',
    'headers' => [
        'X-API-Key'   => env('FOGOS_API_KEY'),
        'User-Agent'  => 'MinhaApp/1.0 (+contacto@dominio.pt)',
        'X-Origin-IP' => env('APP_PUBLIC_IP'),  // opcional
    ],
    'connect_timeout' => 10,
    'timeout' => 30,
]);
```

Exemplo (fetch / JS):

```js
const res = await fetch('https://api.fogos.pt/v2/weather/observations', {
  headers: {
    'X-API-Key': process.env.FOGOS_API_KEY,
    'User-Agent': 'MinhaApp/1.0 (+contacto@dominio.pt)',
  },
});
```

O token nunca deve ficar em código versionado — usar sempre variável de ambiente / secret manager.

---

## 3. Tabela de correspondência de endpoints

Base URL: `https://api.fogos.pt`. Todos os endpoints em baixo são `GET` e devolvem `application/json`. **O shape do payload é idêntico ao upstream do IPMA**, salvo as notas listadas.

| IPMA (a substituir) | FogosPT (novo) | Notas de paridade |
|---|---|---|
| `https://api.ipma.pt/open-data/observation/meteorology/stations/stations.json` | `GET /v2/weather/stations/ipma` | Array de GeoJSON Features com `geometry.coordinates` `[lng, lat]`, `properties.idEstacao`, `properties.localEstacao`. **Idêntico** ao upstream. Cache servidor 1 h. |
| `https://api.ipma.pt/open-data/observation/meteorology/stations/observations.json` | `GET /v2/weather/observations` | Objecto `{ "YYYY-MM-DDTHH:mm" (UTC): { "<stationId>": { intensidadeVentoKM, temperatura, radiacao, idDireccVento, precAcumulada, intensidadeVento, humidade, pressao } } }`. Campos numéricos em falta são reportados como `-99` (paridade IPMA). **Diferença conhecida:** estações sem qualquer leitura numa hora **não aparecem** (upstream reporta-as como `null`). Cache servidor 10 min. |
| `https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json` | `GET /v2/warnings/ipma` | Array de warnings com `text`, `awarenessTypeName`, `idAreaAviso`, `startTime`, `awarenessLevelID`, `endTime`. **Diferença conhecida:** só devolve warnings com `endTime >= agora` (o upstream inclui algumas já expiradas) e nunca inclui nível `green` (nunca é ingerido). Ordenado por `startTime` ascendente. |
| `https://api.ipma.pt/open-data/forecast/meteorology/rcm/rcm-d0.json` | `GET /v2/rcm/ipma/d0` | Objecto `{ dataPrev, dataRun, fileDate, local: { "<dico>": { data: { rcm }, dico, latitude, longitude } } }`. **Idêntico** ao upstream. Cache servidor 10 min. |
| `https://api.ipma.pt/open-data/forecast/meteorology/rcm/rcm-d1.json` | `GET /v2/rcm/ipma/d1` | Idem para o dia +1. |

Notas gerais:
- **Cache do servidor:** todos os endpoints são cacheados em Redis do lado do servidor. Consumidores devem **remover cache local próprio** para estas chamadas se ele existia apenas para reduzir carga no IPMA — passa a ser redundante e piora a frescura dos dados.
- **Códigos de erro:** os endpoints devolvem `500` em falha interna. `/v2/rcm/ipma/d{day}` devolve `404` se ainda não houver dados persistidos. Autenticação em falta ou token inválido pode devolver `401`/`403` no futuro.

---

## 4. Passos por consumidor (checklist para o agente)

Executar **por cada consumidor**, na ordem apresentada:

1. **Localizar todas as referências a `api.ipma.pt`** no repositório:
   ```bash
   grep -rn "api.ipma.pt" --include='*.php' --include='*.js' --include='*.ts' --include='*.dart' --include='*.py' --include='*.kt' --include='*.swift' .
   ```
   Também procurar em ficheiros de configuração (`.env.example`, `config/*.php`, `application.yml`, etc.) por URLs base ou por chaves como `IPMA_URL`, `IPMA_BASE`, `IPMA_STATIONS_URL`, etc.

2. **Confirmar que cada match corresponde a um dos 5 endpoints da tabela acima.** Se aparecer uma URL do IPMA que **não** está na tabela (ex.: `forecast/meteorology/daily`, previsão marítima, sismos), **não migrar** — reportar de volta para se avaliar se se deve expor um novo endpoint na FogosPT API. Não inventar mapeamentos.

3. **Configurar o cliente HTTP partilhado** (ver §2) com `base_uri = https://api.fogos.pt` e os cabeçalhos obrigatórios. Se o consumidor tiver múltiplos clientes (um para IPMA, um para outros), migrar as chamadas IPMA para o cliente da FogosPT sem tocar nos outros.

4. **Substituir as URLs** por caminhos relativos ao novo `base_uri`. Ex.: `https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json` → `/v2/warnings/ipma`.

5. **Ajustar o parsing quando aplicável.** A maioria dos consumidores não precisa mudar nada; verificar apenas:
   - Consumidores de `observations` que assumam presença explícita de todas as estações em cada hora (mesmo que a null) — passam a ver só as que têm leitura. Iterar defensivamente sobre as chaves presentes.
   - Consumidores de `warnings` que dependessem de warnings já expiradas ou nível `green` — deixam de os receber. Isto é geralmente o comportamento desejado.

6. **Extrair `FOGOS_API_KEY`, `FOGOS_USER_AGENT` e (opcional) `FOGOS_ORIGIN_IP` para variáveis de ambiente.** Adicionar aos ficheiros de exemplo (`.env.example`, `config/*.example.*`) **sem valor real**. Actualizar deployment/secret manager fora do repositório.

7. **Adicionar logging estruturado** dos códigos de resposta (200, 401, 403, 429, 5xx) na camada do cliente HTTP. Sem isto, um bloqueio silencioso na FogosPT API passa despercebido.

8. **Timeouts:** garantir que o cliente HTTP tem `connect_timeout ≤ 10s` e `timeout ≤ 30s`, para não pendurar o processo consumidor se a FogosPT API estiver lenta.

9. **Actualizar documentação interna do consumidor** (README, runbook) a apontar para <https://fogos.pt/pt/api> em vez de para IPMA, e a listar `FOGOS_API_KEY` como variável obrigatória.

10. **Commit atómico por consumidor**, com mensagem no formato:
    ```
    feat: migrate IPMA <endpoint> consumption to FogosPT API

    Replaces direct api.ipma.pt calls with the shape-compatible FogosPT
    endpoints (see docs/migration-from-ipma.md in fogosapi). Adds
    X-API-Key / User-Agent headers.
    ```

---

## 5. Verificação (obrigatória antes de considerar feito)

Para **cada endpoint migrado**, o agente deve fazer o seguinte:

1. **Diff funcional 1x manual:** correr o consumidor contra o IPMA e contra a FogosPT em paralelo (feature flag ou branch) e comparar o resultado da funcionalidade downstream (ex.: contagem de warnings mostradas no mapa, valores de temperatura mostrados por estação). Divergências para além das listadas na tabela §3 são bugs — reportar antes de fazer merge.

2. **Snapshot / contract test:** se o consumidor tem testes, adicionar/actualizar um teste que fixe a resposta esperada da FogosPT para pelo menos um endpoint (fixtures gravados). Isto detecta regressões silenciosas.

3. **Correr sem token / com User-Agent default:** confirmar que o consumidor **falha ruidosamente** (log, alerta, exit code ≠ 0) — não silenciosamente. Um consumidor que não repara que ficou bloqueado é o pior cenário.

4. **Confirmar que não sobrou nenhuma chamada directa ao IPMA:**
   ```bash
   grep -rn "api.ipma.pt" --include='*.php' --include='*.js' --include='*.ts' --include='*.dart' --include='*.py' --include='*.kt' --include='*.swift' .
   ```
   Deve devolver zero linhas (excepto comentários / docs / URLs em textos de mensagens ao utilizador).

---

## 6. Plano de rollback

Se após deploy houver quebra grave e não for possível diagnosticar rapidamente:

- **Preferido:** manter durante 1 sprint uma variável `FOGOS_MIGRATION_ENABLED=true|false` que alterne o `base_uri` entre `https://api.fogos.pt` e `https://api.ipma.pt` (respeitando os shapes documentados). Rollback é uma alteração de env var, sem redeploy.
- **Alternativa:** reverter o commit da migração (`git revert <sha>`).

Uma vez estabilizado (recomendado ≥ 2 semanas em produção sem incidentes), remover o fallback e o env var.

---

## 7. Fora de âmbito desta migração

- Endpoints do IPMA que **não** estão listados na tabela §3 (previsão diária, marés, sismos, satélite, etc.) — mantêm-se em consumo directo do IPMA até serem também expostos pela FogosPT. Se o volume de consumo desses justificar, abrir issue no `fogosapi` a pedir novo endpoint em vez de inventar.
- Chamadas do lado do frontend público (browser) que não podem esconder o `X-API-Key` — nesses casos manter IPMA ou introduzir um proxy próprio; não expor o token.
