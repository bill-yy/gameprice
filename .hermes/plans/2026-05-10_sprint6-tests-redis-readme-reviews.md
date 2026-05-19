# Sprint 6: Tests Pest + Redis Cache + README + Reviews

## Build
- Crear plan de 4 tareas paralelas (máx 3 agentes, agrupando README+Reviews)
- Preparar contexto para cada agente

## Measure
- Cobertura de tests ejecutados
- Tiempo de respuesta cacheado vs sin cache
- Calidad del README generado
- Funcionalidad de reviews

## Analyze
- Tests pasan?
- Redis cache funciona en local y Dokploy?
- README cubre todo el stack y deploy?
- Reviews persisten y se muestran?

## Decide
- Commit y push a GitHub
- Reintentar deploy Dokploy si GitHub conectado

---

### Tarea A: Tests Pest (Agente 1)
- Unit tests: BaseAffiliateService, cada AffiliateService
- Feature tests: GameController, GenreController, PriceAlertController
- Test de comandos: ScrapeAllPrices, CheckPriceAlerts
- Test de modelos: relaciones, scopes, factories

### Tarea B: Cache Redis (Agente 2)
- Configurar Redis en docker-compose (local) y Dokploy
- Cachear resultados de prices:scrape-all (TTL 3h)
- Cachear Home page data (TTL 1h)
- Cachear GameShow data (TTL 30min)
- Comando `cache:clear-prices`

### Tarea C: README Profesional + Reviews (Agente 3)
- README.md completo con badges, stack, instalación, deploy, monetización
- Sistema de reviews: migración `reviews`, modelo `Review`, controller, componente Vue
- Rating por estrellas (1-5), comentarios, paginación
- Mostrar reviews en GameShow.vue
