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
- App env: `S3_ENDPOINT=http://fogos.s3:9000`, `S3_ACCESS_KEY` / `S3_SECRET_KEY` (app, bucket-scoped), optional `S3_ADMIN_*`, `S3_BUCKET=incident-photos`, `S3_PUBLIC_BASE_URL=https://cdn.fogos.pt/incident-photos`. `MINIO_*` names still work as a fallback so the code can be deployed before the .env cutover.
- After cutover, `fogos.s3` owns `127.0.0.1:9000`, the same port MinIO used.

The `chrislusf/seaweedfs:4.54` image is Alpine (it has `/bin/sh` and `curl`, which the healthcheck uses). Our script writes `s3.json` and then execs the image's own `/entrypoint.sh`, which chowns `/data` to uid 1000 (`seaweed`) and drops root. Expect `/data-s3` on the host to end up owned by 1000:1000.

The image also ships `/etc/seaweedfs/filer.toml`, which stores filer metadata under `/data/filerldb2`. Keep `-dir=/data` on the same volume; if you ever change `-dir`, keep that metadata path on the persistent mount or buckets will look empty after a recreate.


## Copy and cutover

Do this on the production host. Estimated downtime is only the final nginx / env switch; the copy itself happens while MinIO is still serving reads.

1. Finish the #74 backups: `docker save minio/minio:RELEASE.2023-09-30T07-02-29Z | gzip` off the host, and a copy of `/data` (bucket `incident-photos`).
2. Put the new env vars in `.env` (generate a dedicated app key and, optionally, an admin key — do not reuse `MINIO_ROOT_*` for the app). Leave `MINIO_ENDPOINT` pointing at MinIO until step 6 so a premature php-fpm reload does not send writes to an empty store. If you deploy this code first, the `MINIO_*` fallback keeps the live disk working.
3. Start both stores, with MinIO staying on host port 9000:

   ```bash
   mkdir -p /data-s3
   docker-compose -f docker-compose.yml -f docker-compose.minio-migrate.yml up -d fogos.minio fogos.s3
   ```

   SeaweedFS is then on `127.0.0.1:9002` (and `http://fogos.s3:9000` on the compose network). MinIO stays on `127.0.0.1:9000`.
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

   Keep MinIO running read-write or switch it to read-only at your discretion; a final sync in step 6 catches objects written during the copy. Confirm a known old key is readable *anonymously* from SeaweedFS (this is the CDN contract):

   ```bash
   curl -sI "http://127.0.0.1:9002/incident-photos/incidents/<fireId>/<photoId>.jpg"
   ```

   That must be `200` with `Content-Type: image/jpeg`, and no request signing.
5. Point a one-off test upload at SeaweedFS (or use `rclone copy` / `aws --endpoint-url http://127.0.0.1:9002 s3 cp`) and GET it the same way.
6. Final `rclone sync`, then cut over:

   - Set `S3_ENDPOINT=http://fogos.s3:9000` (and the new keys / public URL) in `.env`.
   - Reload php-fpm (`docker-compose kill -s USR2 fogos.api`) and `php artisan queue:restart` so workers pick up the new disk.
   - Drop the migrate overlay so SeaweedFS takes host port 9000 (the port the CDN / host nginx already uses):

     ```bash
     docker-compose up -d fogos.s3
     docker-compose -f docker-compose.yml -f docker-compose.minio-migrate.yml stop fogos.minio
     ```

   - Verify an *old* photo URL and a *new* upload through `https://cdn.fogos.pt/incident-photos/…`, plus moderation approve (workers `get` the object) and delete.
7. Leave the MinIO container stopped and keep `/data` and the `docker save` image until you are sure. Then remove `fogos.minio` from any leftover override and, only after that, decide when `/data` can be archived off the host.

## #75 — scoped key

The app identity `fogos-app` is limited to `Read`, `Write`, `List`, `Tagging`, `ReadAcp`, and `WriteAcp` on `incident-photos`. It is not an `Admin` key. `WriteAcp` is there because Flysystem's `visibility: public` sends `x-amz-acl`. Anonymous access is only `Read` on that bucket (no `List`, so the bucket cannot be enumerated without credentials). The optional `fogos-admin` identity is for rclone/ops; leave `S3_ADMIN_*` empty if you do not want it.

## If an upload fails on ACL

If `PhotoStorageTool::store()` starts failing because the store rejects `x-amz-acl: public-read`, drop `'visibility' => 'public'` from the `put` options (and the disk config). Public reads already come from the anonymous identity and the CDN, not from object ACLs.
