# Deploy en Dokploy

## Requisitos
- Servidor Dokploy con acceso
- Base de datos PostgreSQL creada en Dokploy
- Dominio configurado

## Pasos

### 1. Crear proyecto en Dokploy
1. Ir a tu panel de Dokploy
2. Crear nuevo proyecto > Aplicación
3. Seleccionar repositorio `bill-yy/gameprice` (o tu fork)
4. Rama: `master`

### 2. Variables de entorno
Configurar en el panel de Dokploy > Environment:

```
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_URL=https://tu-dominio.com
DB_HOST=postgresXXXXXXXX
DB_PORT=5432
DB_DATABASE=gameprice
DB_USERNAME=gameprice
DB_PASSWORD=XXXXXXXX
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*
```

Generar `APP_KEY`:
```bash
php artisan key:generate --show
```

### 3. Docker Compose
Dokploy usa el `docker-compose.prod.yml` automáticamente si existe.

Asegúrate de que el build context esté correcto:
- Build path: `./`
- Dockerfile: `docker/Dockerfile`

### 4. Base de datos
1. Crear PostgreSQL service en Dokploy
2. Conectar a la app
3. Ejecutar migraciones desde Dokploy terminal:
```bash
php artisan migrate --force
php artisan db:seed --class=StoreSeeder
```

### 5. Comandos post-deploy
Configurar en Dokploy > Deploy > Post-deployment commands:
```bash
php artisan migrate --force
php artisan optimize
```

### 6. Scheduler (opcional)
Para ejecutar el scheduler de precios cada 6 horas, añadir un cron job en Dokploy:
```bash
php artisan schedule:run
```
O usar un servicio de cron separado.

## Salud
El endpoint `/up` está configurado como health check en `bootstrap/app.php`.
