<?php

use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\CrudController;
use App\Controllers\DashboardController;
use App\Controllers\RecommendationController;
use App\Controllers\SignalController;
use App\Core\Router;

$router = new Router();

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/', [DashboardController::class, 'index']);
$router->get('/command/southeast', [DashboardController::class, 'southeast']);
$router->get('/command/great-lakes', [DashboardController::class, 'greatLakes']);
$router->get('/signals', [SignalController::class, 'index']);
$router->post('/signals', [SignalController::class, 'save']);
$router->post('/signals/status', [SignalController::class, 'updateStatus']);
$router->post('/signals/convert', [SignalController::class, 'convert']);
$router->get('/organizations', [CrudController::class, 'organizations']);
$router->post('/organizations', [CrudController::class, 'saveOrganization']);
$router->post('/delete', [CrudController::class, 'delete']);
$router->get('/contacts', [CrudController::class, 'contacts']);
$router->post('/contacts', [CrudController::class, 'saveContact']);
$router->get('/subcontractors', [CrudController::class, 'subcontractors']);
$router->post('/subcontractors', [CrudController::class, 'saveSubcontractor']);
$router->get('/opportunities', [CrudController::class, 'opportunities']);
$router->post('/opportunities', [CrudController::class, 'saveOpportunity']);
$router->get('/recommendations', [RecommendationController::class, 'index']);
$router->post('/recommendations', [RecommendationController::class, 'update']);
$router->post('/recommendations/regenerate', [RecommendationController::class, 'regenerate']);
$router->get('/activities', [ActivityController::class, 'index']);
$router->post('/activities', [ActivityController::class, 'save']);
$router->get('/settings', [DashboardController::class, 'settings']);
$router->post('/settings/targets', [DashboardController::class, 'saveTargets']);
$router->get('/record', [ActivityController::class, 'record']);

return $router;
