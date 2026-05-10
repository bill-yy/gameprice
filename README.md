# GamePrice - Comparador de Precios de Videojuegos

[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Pest](https://img.shields.io/badge/Pest-3-FF3E4D?logo=pest&logoColor=white)](https://pestphp.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white)](https://docker.com)
[![Dokploy](https://img.shields.io/badge/Dokploy-Deploy-7C3AED?logo=dokploy&logoColor=white)](https://dokploy.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Sitio web automatizado 100% técnico para comparar precios de videojuegos. Sin contenido editorial. Stack moderno.

## Características

- **Comparador de precios en tiempo real** — 6 tiendas afiliadas (Eneba, Instant Gaming, Fanatical, CDKeys, G2A, Humble Bundle)
- **Alertas de precio por email** — Sistema freemium con notificaciones automáticas
- **SEO automático** — Schema.org (JSON-LD), OpenGraph, Sitemap XML
- **Páginas de categoría por género** — Navegación filtrada y optimizada
- **Sistema de reviews con rating de estrellas** — Reseñas de usuarios con validación
- **Panel de administración** — Gestión completa del catálogo
- **Modo oscuro** — Interfaz oscura por defecto
- **Responsive** — Diseño adaptativo para móvil, tablet y escritorio
- **Cache Redis** — Respuestas rápidas con caché inteligente

## Screenshots

<!-- Add screenshots here -->
| Página principal | Ficha de juego |
|---|---|
| *Pending* | *Pending* |

## Tech Stack

| Capa | Tecnología |
|---|---|
| **Backend** | Laravel 12, PHP 8.4 |
| **Frontend** | Vue 3, Inertia.js, Tailwind CSS 4 |
| **Base de datos** | MySQL 8 |
| **Cache** | Redis |
| **Testing** | Pest 3 |
| **Infraestructura** | Docker, Dokploy |
| **Scraping** | Artisan commands (Steam API + scrapers) |

## Instalación

```bash
# Clonar el repositorio
git clone https://github.com/your-user/gameprice.git
cd gameprice

# Levantar servicios con Docker
docker-compose up -d

# Instalar dependencias
composer install
npm install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Ejecutar migraciones y seeders
php artisan migrate
php artisan db:seed

# Actualizar catálogo desde Steam
php artisan steam:update-games

# Obtener precios de todas las tiendas
php artisan prices:scrape-all

# Compilar assets
npm run build
```

## Despliegue (Dokploy)

1. Crear un nuevo proyecto en Dokploy
2. Conectar el repositorio Git
3. Configurar las variables de entorno (`.env`):
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `REDIS_HOST`, `REDIS_PASSWORD`
   - `STEAM_API_KEY`
   - `SCRAPING_API_KEY` (si aplica)
4. Configurar el build pack como **Dockerfile** o **Nixpacks**
5. Desplegar con un click desde el panel

## Monetización

- **Programas de afiliados**: Enlaces de referencia a tiendas (Eneba, Instant Gaming, Fanatical, etc.)
- **Google AdSense**: Banners publicitarios estratégicos

## Contribuir

Este proyecto sigue el **BMAD Method** para desarrollo agéntico.

1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commits descriptivos siguiendo convenciones
4. Abrir Pull Request

## Licencia

Este proyecto está licenciado bajo la [MIT License](https://opensource.org/licenses/MIT).
