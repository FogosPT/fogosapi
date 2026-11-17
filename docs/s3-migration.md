# Migrating incident photos from MinIO to SeaweedFS

This is the cutover for [#73](https://github.com/FogosPT/fogosapi/issues/73). It also lands the scoped app key from [#75](https://github.com/FogosPT/fogosapi/issues/75). Keep [#74](https://github.com/FogosPT/fogosapi/issues/74) (image + `/data` backup) done *before* you start, and do not run `docker image prune -a` / `docker system prune -a` while MinIO is still the live store.

Public photo URLs stay `https://cdn.fogos.pt/incident-photos/<key>` (for example `…/incident-photos/incidents/<fireId>/<photoId>.jpg`). The app never used presigned URLs; `PhotoStorageTool::publicUrl()` builds that string from `S3_PUBLIC_BASE_URL`. The host / CDN nginx that already proxies path-style GETs to `127.0.0.1:9000` does not need a rewrite if SeaweedFS is the process on that port after cutover.

## Why SeaweedFS

MinIO `RELEASE.2023-09-30T07-02-29Z` is unpullable and upstream is archived. Of the two options named in #73:

- **SeaweedFS** speaks path-style S3 and can grant `anonymous` `Read` on `incident-photos`, so existing CDN URLs keep working with no nginx Host-header mapping.
- **Garage** is smaller, but it still rejects anonymous S3 (`Forbidden: Garage does not support anonymous access yet`). Public reads have to go through its website endpoint, which is host-based, so the CDN nginx would have to map `/incident-photos/…` onto that endpoint.

A still-pullable MinIO-lineage image (`docker.io/pgsty/silo`) would be a lower-friction stopgap (same `MINIO_*` env, same `/data` layout, current security builds) but it is a community fork of the stack #73 is trying to leave. This repo switches to SeaweedFS.

The open-source SeaweedFS binary (Apache-2.0, image `chrislusf/seaweedfs`) is what compose pins (`4.54`). That is not SeaweedFS Enterprise, which is a separate paid product.

## What changes in compose and .env

- New service `fogos.s3` (`chrislusf/seaweedfs:4.54`) with a scoped app key and optional admin key. The entrypoint writes `/etc/seaweedfs/s3.json` from env (nothing secret is in git).
- New bind mount `/data-s3` — do **not** point this at the existing MinIO `/data`; the on-disk formats are not compatible.
- App env: `S3_ENDPOINT=http://fogos.s3:9000`, `S3_ACCESS_KEY` / `S3_SECRET_KEY` (app, bucket-scoped), optional `S3_ADMIN_*`, `S3_BUCKET=incident-photos`, `S3_PUBLIC_BASE_URL=https://cdn.fogos.pt/incident-photos`. The app uses the `S3_*` disk only when `S3_ENDPOINT` is set; otherwise it keeps the `MINIO_*` endpoint and credentials together, so the code (and the SeaweedFS container keys) can be deployed before the .env cutover.
- Host port: `fogos.s3` publishes `127.0.0.1:${S3_HOST_PORT:-9000}:9000`. During the copy, set `S3_HOST_PORT=9002` so MinIO can keep 9000. After cutover, unset it (or set it back to 9000) so `fogos.s3` owns `127.0.0.1:9000`, the same port MinIO used.

The `chrislusf/seaweedfs:4.54` image is Alpine (it has `/bin/sh` and `curl`, which the healthcheck uses). Our script writes `s3.json` and then execs the image's own `/entrypoint.sh`, which chowns `/data` to uid 1000 (`seaweed`) and drops root. Expect `/data-s3` on the host to end up owned by 1000:1000.

The image also ships `/etc/seaweedfs/filer.toml`, which stores filer metadata under `/data/filerldb2`. Keep `-dir=/data` on the same volume; if you ever change `-dir`, keep that metadata path on the persistent mount or buckets will look empty after a recreate.


## Copy and cutover

Do this on the production host. The copy itself happens while MinIO is still serving reads. Photo *writes* (uploads, deletes, and moderation approve/reject) must be paused around the final sync and env switch; public CDN reads can stay on port 9000 throughout, aside from the brief window while that port is rebound to SeaweedFS.

1. Finish the #74 backups: `docker save minio/minio:RELEASE.2023-09-30T07-02-29Z | gzip` off the host, and a copy of `/data` (bucket `incident-photos`).
2. Put the new env vars in `.env` (generate a dedicated app key and, optionally, an admin key — do not reuse `MINIO_ROOT_*` for the app). You can stage `S3_ACCESS_KEY`, `S3_SECRET_KEY`, optional `S3_ADMIN_*`, and `S3_BUCKET` now: compose needs them to start SeaweedFS. Leave `S3_ENDPOINT` unset and keep `MINIO_ENDPOINT` pointing at MinIO until step 6. The app selects the `S3_*` or `MINIO_*` endpoint and credentials as a set, so a premature php-fpm reload during the copy will not send the new keys to MinIO (and will not send writes to an empty SeaweedFS bucket). If you deploy this code first, that `MINIO_*` pairing keeps the live disk working.
3. Start both stores, with MinIO staying on host port 9000. Compose concatenates `ports` across files, so the migrate overlay does not add a second mapping; set `S3_HOST_PORT=9002` so the base file publishes SeaweedFS only on 9002:

   ```bash
   mkdir -p /data-s3
   S3_HOST_PORT=9002 docker-compose -f docker-compose.yml -f docker-compose.minio-migrate.yml up -d fogos.minio fogos.s3
   ```

   You can also put `S3_HOST_PORT=9002` in `.env` for the copy window (remove it, or set it back to 9000, before step 6). SeaweedFS is then on `127.0.0.1:9002` (and `http://fogos.s3:9000` on the compose network). MinIO stays on `127.0.0.1:9000`.
4. Copy the bucket. `rclone` is enough; `mc mirror` also works if you still have a client.

   ```bash
   # rclone.conf (paths are examples)
   # [minio]
   # type = s3
   # provider = Minio
   # env_auth = false
   # access_key_id = <MINIO_ROOT_USER>
   # secret_access_key = <MINIO_ROOT_PASSWORD>
   # endpoint = http://127.0.0.1:9000
   # acl = private
   #
   # [seaweed]
   # type = s3
   # provider = Other
   # env_auth = false
   # access_key_id = <S3_ACCESS_KEY or S3_ADMIN_ACCESS_KEY>
   # secret_access_key = <matching secret>
   # endpoint = http://127.0.0.1:9002
   # acl = private

   rclone sync minio:incident-photos seaweed:incident-photos --checksum --dry-run
   rclone sync minio:incident-photos seaweed:incident-photos --checksum
   ```

   Keep MinIO running read-write during this first copy if you want; step 6 pauses writes and does a final sync so objects accepted in the meantime are not left only on MinIO. Confirm a known old key is readable *anonymously* from SeaweedFS (this is the CDN contract):

   ```bash
   curl -sI "http://127.0.0.1:9002/incident-photos/incidents/<fireId>/<photoId>.jpg"
   ```

   That must be `200` with `Content-Type: image/jpeg`, and no request signing.
5. Point a one-off test upload at SeaweedFS (or use `rclone copy` / `aws --endpoint-url http://127.0.0.1:9002 s3 cp`) and GET it the same way.
6. Quiesce photo writes, final `rclone sync`, move port 9000, then switch the app:

   - Pause photo uploads, deletes, and moderation approve/reject so nothing is written only to MinIO after the final sync. Public list/GET and CDN reads can stay up. A targeted nginx `503` on these routes is enough (they all live under `/v2`):
     - `POST /v2/incidents/{id}/photos`
     - `POST /v2/photos/{photoId}/delete`
     - `POST /v2/moderation/photos/{photoId}/approve`
     - `POST /v2/moderation/photos/{photoId}/reject`
     Alternatively, `php artisan down` pauses the whole API. Drain in-flight requests after the pause (watch php-fpm / nginx access logs, or wait a few seconds past the upload timeout) before the sync below.
   - Final `rclone sync minio:incident-photos seaweed:incident-photos --checksum`. Re-check a known old key anonymously on `127.0.0.1:9002`.
   - Stop MinIO and remove the overlay service *before* SeaweedFS is rebound to host port 9000. Dropping the overlay file from the `up` command does not stop `fogos.minio` unless you pass `--remove-orphans` or stop it first; starting SeaweedFS on 9000 while MinIO still holds that port will fail, and stopping MinIO afterwards would leave no store on the CDN port.

     ```bash
     S3_HOST_PORT=9002 docker-compose -f docker-compose.yml -f docker-compose.minio-migrate.yml stop fogos.minio
     S3_HOST_PORT=9002 docker-compose -f docker-compose.yml -f docker-compose.minio-migrate.yml rm -f fogos.minio
     # Unset S3_HOST_PORT (or set it to 9000) if you added it to .env.
     unset S3_HOST_PORT
     docker-compose -f docker-compose.yml up -d --remove-orphans fogos.s3
     ```

   - Wait until SeaweedFS is healthy on the CDN port before switching application traffic:

     ```bash
     # compose healthcheck is up, and an old object is anonymously readable
     docker-compose exec fogos.s3 curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:9000/
     curl -sI "http://127.0.0.1:9000/incident-photos/incidents/<fireId>/<photoId>.jpg"
     ```

     That GET must be `200` with `Content-Type: image/jpeg`.
   - Set `S3_ENDPOINT=http://fogos.s3:9000` (and the new keys / public URL) in `.env`. This is the cutover: the app now uses the `S3_*` values as a set.
   - Reload php-fpm (`docker-compose kill -s USR2 fogos.api`) and `php artisan queue:restart` so workers pick up the new disk.
   - Verify an *old* photo URL and a *new* upload through `https://cdn.fogos.pt/incident-photos/…`, plus moderation approve (workers `get` the object) and delete.
   - Resume photo writes (remove the nginx `503`s, or `php artisan up`).
7. Leave the MinIO container stopped and keep `/data` and the `docker save` image until you are sure. Then remove `fogos.minio` from any leftover override and, only after that, decide when `/data` can be archived off the host.

## #75 — scoped key

The app identity `fogos-app` is limited to `Read`, `Write`, `List`, `Tagging`, `ReadAcp`, and `WriteAcp` on `incident-photos`. It is not an `Admin` key. `WriteAcp` is there because Flysystem's `visibility: public` sends `x-amz-acl`. Anonymous access is only `Read` on that bucket (no `List`, so the bucket cannot be enumerated without credentials). The optional `fogos-admin` identity is for rclone/ops; leave `S3_ADMIN_*` empty if you do not want it.

## If an upload fails on ACL

If `PhotoStorageTool::store()` starts failing because the store rejects `x-amz-acl: public-read`, drop `'visibility' => 'public'` from the `put` options (and the disk config). Public reads already come from the anonymous identity and the CDN, not from object ACLs.
