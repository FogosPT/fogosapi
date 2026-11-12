# AEMET (Espanha): avisos e estações meteorológicas — desenho

Issue: #102 · Estado: rascunho para aprovação

## Porquê

Os fenómenos meteorológicos não olham para fronteiras. Quem acompanha os avisos de
Bragança, da Guarda ou de Faro no avisos.pt quer saber se do outro lado também há
aviso e com que severidade. A fogosapi já importa os avisos e as estações do IPMA;
passa a importar também os da AEMET (Agencia Estatal de Meteorología), com
**endpoints próprios para Espanha** (pedido explícito: nada se mistura nos
endpoints do IPMA).

Consumidor principal: avisos.pt — zonas de aviso espanholas coloridas no `/mapa`
(toda a Espanha peninsular) e uma secção "Do outro lado da fronteira" nas páginas dos
distritos raianos. Esse trabalho tem desenho próprio, depois deste; este documento só
cobre a fogosapi.

## Objetivos

1. Importar os avisos da AEMET (toda a Espanha), guardando todas as mensagens CAP
   recebidas (histórico completo) e servindo os avisos em vigor.
2. Servir a geometria das zonas de aviso.
3. Importar o inventário e as observações horárias das estações da AEMET e servi-las.

## Não-objetivos

- Mostrar o que quer que seja no avisos.pt (desenho seguinte).
- Registo de alterações estilo `weather_warning_events` / endpoint de histórico de
  avisos espanhóis. Os dados para o construir ficam guardados (mensagens CAP com
  `references`), mas o endpoint fica para quando houver quem o consuma.
- Resumos diários climatológicos da AEMET (`valores/climatologicos/diarios`), normais,
  deteção de ondas de calor/frio para Espanha.
- Notificações (Telegram, X, Bluesky…) de avisos espanhóis.
- Juntar estações espanholas às do IPMA em `/v2/weather/*`.

## Anti-objetivos

- Mexer no comportamento ou no formato dos endpoints do IPMA.
- Pôr a chave da AEMET no repositório (vive só em `.env`).
- Um job que, com a AEMET em baixo ou um feed vazio, apague ou dê por terminados os
  avisos em vigor.

## Fonte: AEMET OpenData

- Base `https://opendata.aemet.es/opendata/api/`, chave no header `api_key`
  (`AEMET_API_KEY`). Sem chave os jobs não correm (como `MADEIRA_EVENTS_URL`).
- **Pedido em dois passos**: a API devolve `{descripcion, estado, datos, metadatos}`;
  os dados estão no URL `datos` (curta duração), pedido logo a seguir.
  `estado` ≠ 200 é falha. Sem chave a API responde HTTP 200 com corpo vazio; com
  chave inválida/expirada, 401 — ambos são falha, e o 401 tem mensagem própria no
  Discord ("chave AEMET inválida ou expirada").
- A chave atual é um JWT que **expira a 2027-02-20**; tem de ser renovada em
  https://opendata.aemet.es/centrodedescargas/altaUsuario (email + captcha). Fica
  escrito no CLAUDE.md.
- Rate limit por minuto (HTTP 429, sem número publicado); os jobs fazem 1–2 pedidos
  por execução, longe do limite. 429 = falha da execução, sem retry imediato.
- Licença (nota legal AEMET, Lei 37/2007): reutilização livre, também comercial,
  citando a fonte — "Fuente: AEMET" / "Información elaborada utilizando, entre otras,
  la obtenida de la Agencia Estatal de Meteorología". Acrescentar ao `NOTICE` e à
  documentação da API (`docs/`).

Formatos verificados a 2026-11-12 com a chave real.

## Avisos

### Fonte

`avisos_cap/ultimoelaborado/area/esp` → `datos` é um **tar** (GNU, não comprimido,
Content-Type `application/x-gtar`) com um ficheiro CAP 1.2 XML (UTF-8) por mensagem.
Amostra de 2026-11-12: 284 ficheiros, 233 zonas distintas.

Cada mensagem (`<alert>`):
- `identifier` (único, imutável), `sent`, `msgType` (`Alert` | `Update`; `Cancel`
  possível pela norma), `references` (lista separada por espaços de
  `sender,identifier,sent` das mensagens que esta substitui).
- Dois `<info>`: `es-ES` e `en-GB`. Em cada um: `event`, `headline`, `description`,
  `instruction`, `onset`, `expires`, `effective` (com offset, hora de Madrid),
  `eventCode` `AEMET-Meteoalerta fenomeno` = `"VI;Vientos"`, parâmetros
  `AEMET-Meteoalerta nivel` (`verde|amarillo|naranja|rojo`),
  `AEMET-Meteoalerta parametro` (`"RM;Rachas máximas;80 km/h"`),
  `AEMET-Meteoalerta probabilidad` (`"40%-70%"`).
- Um ou mais `<area>`: `areaDesc` (nome), `<polygon>` (`lat,lon` separados por
  espaços, 2 casas decimais, fechado) e geocode `AEMET-Meteoalerta zona` (código).
- O tar inclui mensagens **`verde`** (sem aviso), que cobrem todas as zonas — é daí
  que vem a geometria de todas as zonas — e mensagens já expiradas.

### Zonas

Código = CCAA (2) + província INE (2) + zona (2), com sufixo `C` nas zonas costeiras
(`713603` Miño de Pontevedra, `713603C` Costa - Miño de Pontevedra).
Prefixos `64` Baleares, `65` Canárias, `78` Ceuta, `79` Melilla → `peninsula = false`;
todos os outros → `true`.

Modelo `AemetWarningZone` (coleção `aemetWarningZones`), upsert por `code` a partir de
**todas** as áreas de todas as mensagens (incluindo `verde`) em cada execução:
`code`, `name` (`areaDesc`), `ccaa` (2 primeiros dígitos), `province` (dígitos 3–4),
`coastal` (sufixo `C`), `peninsula`, `geometry` (GeoJSON **MultiPolygon**, lon/lat —
inverter a ordem do CAP; uma `<area>` pode trazer vários `<polygon>`: na amostra, 156
de 1667 traziam 2), `lastSeenAt`. A geometria é substituída se mudar.

### Arquivo (histórico completo)

Modelo `AemetWarningMessage` (coleção `aemetWarningMessages`), uma por `identifier`,
**nunca alterada depois de criada** (a mensagem CAP é imutável): `identifier`, `sent`,
`msgType`, `references` (array de identifiers), `level`, `phenomenon`, as zonas,
`onset`/`expires` (UTC), e o XML original (`raw`). Uma mensagem que reaparece em
execuções seguintes não é reescrita. É o histórico: tudo o que a AEMET publicou fica
aqui, incluindo `verde` e mensagens substituídas.

Índices: `identifier` único; `zones` + `expires`.

### Avisos em vigor

Derivados do snapshot (o tar é a fotografia completa do que a AEMET tem publicado):

1. Ignorar a execução (não mexer no estado atual) se o tar estiver vazio, não se
   conseguir ler, ou se o `sent` mais recente for anterior ao `sent` mais recente já
   guardado (snapshot velho).
2. Uma mensagem está **substituída** se o seu identifier aparecer em `references` de
   outra mensagem do snapshot, ou se for `Cancel`/referenciada por um `Cancel`.
3. Em vigor = mensagens do snapshot não substituídas, com nível `amarillo`,
   `naranja` ou `rojo`, e `expires >= agora`.

Modelo `AemetWarning` (coleção `aemetWarnings`): uma linha **por mensagem × zona**
(uma mensagem CAP com 16 áreas dá 16 linhas), chave `(identifier, zone)`. Cada
execução reconcilia as linhas ativas com o conjunto em vigor do snapshot: cria as
novas, marca `supersededAt` nas que deixaram de estar (substituídas, canceladas,
desaparecidas do feed). Mesmo princípio do `HandleWeatherWarnings`: a leitura serve
`supersededAt` null e `endTime >= agora`.

Campos: `identifier`, `zone`, `zoneName`, `phenomenon` (código AEMET, ex. `VI`),
`phenomenonName` (es, ex. "Vientos"), `type` (nome IPMA equivalente, ver tabela, ou
null), `level` (`yellow|orange|red`), `startTime` (`onset`), `endTime` (`expires`),
`sent`, `headline`, `description`, `instruction`, `parameter` (texto, ex.
"Rachas máximas: 80 km/h"), `probability`, e o mesmo bloco em inglês (`en`:
`headline`, `description`, `instruction`, `parameter`), `supersededAt`.

Níveis: `amarillo` → `yellow`, `naranja` → `orange`, `rojo` → `red`; `verde` nunca é
aviso.

Fenómenos → tipo IPMA (`awarenessTypeName`, para o avisos.pt usar os mesmos ícones):

| AEMET | `type` |
|---|---|
| VI Vientos | Vento |
| PR Lluvias | Precipitação |
| NE Nevadas | Neve |
| TO Tormentas | Trovoada |
| AT Temperaturas máximas | Tempo Quente |
| BT Temperaturas mínimas | Tempo Frio |
| CO Costeros | Agitação Marítima |
| NI Nieblas | Nevoeiro |
| GA Galernas, RI Rissagas, VS Polvo en suspensión, DH Deshielos, AL Aludes, outros | null (o consumidor usa `phenomenonName`) |

### Job e agenda

`ProcessAemetWarnings` a cada 15 min (como `HandleWeatherWarnings`), `ShouldBeUnique`,
`$timeout` abaixo do `retry_after` (90 s) do Redis — como os jobs da Madeira.
Agendado só com `AEMET_API_KEY` definido. Comando `php artisan aemet:warnings` para
correr síncrono.

### Saúde

`App\Support\AemetFeedHealth`, no molde de `MadeiraFeedHealth`: fontes `warnings` e
`observations`; uma mensagem no canal de erros do Discord (`DiscordTool::postError`)
quando uma fonte falha há mais de `AEMET_STALE_MINUTES` (default 60), outra quando
recupera. Um snapshot ignorado (vazio/velho) conta como falha.

### Endpoints (`AemetController`, cache 60 s)

- `GET v2/warnings/aemet` — avisos em vigor. Filtros: `peninsula=1` (só zonas
  peninsulares), `zone` (CSV de códigos). Sem colapso de sobreposições (assume-se que a AEMET já
  publica uma janela por zona × fenómeno × período; o construtor confirma nas fixtures e, se houver sobreposições, avisa). Horas no mesmo formato que
  `v2/warnings/ipma` (hora de Lisboa, sem offset) para o avisos.pt tratar ambos da
  mesma forma.
- `GET v2/warnings/aemet/zones` — GeoJSON FeatureCollection das zonas
  (`properties`: `code`, `name`, `ccaa`, `province`, `coastal`, `peninsula`).
  Filtro `peninsula=1`. Cache 1 h.

Documentação OpenAPI em `docs/warnings/paths/` + referência em `docs/api.yaml`.

## Estações

### Fonte

- Inventário: `valores/climatologicos/inventarioestaciones/todasestaciones` — 926
  estações (`indicativo`, `nombre`, `provincia`, `latitud`/`longitud` em DMS, ex.
  `"394924N"` / `"025309E"`, `altitud`, `indsinop`).
- Observações: `observacion/convencional/todas` — **últimas 12 h**, uma linha por
  estação × hora (`fint`, UTC), ~850 estações, ~10 500 linhas, JSON em
  **ISO-8859-15** (converter para UTF-8 antes do `json_decode`). Cada linha traz
  `idema`, `ubi`, `lat`, `lon`, `alt` e só os campos que a estação mede.
  Campos a guardar (os restantes ficam em `raw`): `ta`, `tamax`, `tamin` (°C, da
  hora), `prec` (mm na hora), `vv`, `vmax` (m/s), `dv`, `dmax` (graus), `hr` (%),
  `pres`, `pres_nmar` (hPa), `nieve` (cm).

### Modelos

- `AemetStation` (`aemetStations`): `idema` (único), `name`, `province`, `lat`, `lng`,
  `altitude`, `location` (GeoJSON Point, índice 2dsphere), `peninsula` (pela
  província: Illes Balears, Las Palmas, Santa Cruz de Tenerife, Ceuta, Melilla →
  false), `lastSeenAt`. Atualizado do inventário; uma estação que apareça nas
  observações sem estar no inventário é criada com `ubi`/`lat`/`lon`/`alt`.
- `AemetObservation` (`aemetObservations`): upsert por `(idema, date)`; `date` = `fint`
  (UTC); os campos acima com os nomes da AEMET e as unidades originais, mais
  `windKmh` / `gustKmh` (= `vv`/`vmax` × 3.6, arredondado a 1 casa) para não obrigar
  os consumidores a converter. Campo ausente na fonte = ausente (null na API), nunca
  `-99`. Índices: `(idema, date)` único, `date`.

Retenção: guardar tudo (como as do IPMA). Ordem de grandeza: ~850 × 24 × 365 ≈ 7,5 M
documentos/ano.

### Jobs

- `UpdateAemetStations` — diário (inventário).
- `UpdateAemetObservations` — de hora a hora (o feed cobre 12 h, por isso falhas
  isoladas não deixam buracos). `ShouldBeUnique`, timeout como acima.
- Comandos `php artisan aemet:stations` / `aemet:observations` para correr síncrono.

### Endpoints

- `GET v2/weather/aemet/stations` — GeoJSON FeatureCollection das estações
  (`idema`, `name`, `province`, `altitude`, `peninsula`). Cache 1 h.
- `GET v2/weather/aemet/observations` — últimas 24 h, no formato de
  `v2/weather/observations` (`{ "<UTC hora>": { "<idema>": {…} } }`), com os campos
  normalizados: `temperature`, `temperatureMax`, `temperatureMin`, `precipitation`,
  `windKmh`, `gustKmh`, `windDirection`, `humidity`, `pressure`; null quando a
  estação não mede. Cache 10 min.
- `GET v2/weather/aemet/stations/{idema}/observations?hours=` — 1–168 (default 24),
  série horária de uma estação, mesmo formato de campos. 404 se a estação não
  existir. Cache 10 min.

## Configuração

`.env` / `.env.example`: `AEMET_API_KEY` (vazio → jobs AEMET desligados),
`AEMET_STALE_MINUTES` (60). Lidos via `config/services.php` (`services.aemet.*`),
não `env()` direto no código.

## Testes

Fixtures reais reduzidas em `tests/fixtures/aemet/` (um tar pequeno com mensagens
`verde`, `amarillo`, `naranja`, uma `Update` que referencia outra, uma expirada, uma
com várias áreas; um JSON de observações em ISO-8859-15 com acentos; um inventário).
HTTP falso (`Http::fake` ou mock do Guzzle, conforme o padrão dos jobs vizinhos).
Cobrir: dois passos + falhas (vazio, 401, 429, `estado` ≠ 200), snapshot vazio/velho
ignorado, substituição por `references`, mensagem multi-área, verde nunca em vigor,
reconciliação (`supersededAt`), mensagens arquivadas uma só vez, zonas com geometria
lon/lat e `peninsula`, conversão DMS, encoding, upsert das observações, endpoints e
filtros.

## Deixado ao construtor

- Chaves do JSON de `v2/warnings/aemet`: **decisão minha** — os nomes dos campos do
  modelo (`zone`, `type`, `level`, `startTime`…), não as chaves do IPMA
  (`idAreaAviso`, `awarenessTypeName`…); documentadas no OpenAPI. O avisos.pt adapta.
- Nomes exatos de classes/comandos, estrutura interna do parser CAP (SimpleXML/DOM),
  leitura do tar (PharData ou parser próprio — o tar não é comprimido).
- Se o `raw` XML fica no documento da mensagem ou noutro sítio, desde que nada se
  perca.
- Arredondamentos e detalhes de formato das respostas não fixados acima.

## Deploy

Sem dependências novas: a imagem de produção tem a extensão `phar` (`PharData` lê o tar). Passos:
`git pull`, migrations (índices), `AEMET_API_KEY` no `.env` de produção, reload do
php-fpm, `queue:restart`, e confirmar no scheduler. Primeira importação manual com
`aemet:stations`, `aemet:observations`, `aemet:warnings`.
