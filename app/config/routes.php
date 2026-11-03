<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

// Public authentication routes – real, server-side auth (users table). See
// AuthController / app/libraries/Auth.php / app/libraries/GoogleAuth.php.
$router->match('/login',    'AuthController::login',    ['GET', 'POST']);
$router->match('/register', 'AuthController::register', ['GET', 'POST']);
$router->get('/logout',     'AuthController::logout');
$router->match('/forgot-password',        'AuthController::forgot', ['GET', 'POST']);
$router->match('/reset-password/{token}', 'AuthController::reset',  ['GET', 'POST']);
$router->get('/auth/google',          'AuthController::google');
$router->get('/auth/google/callback', 'AuthController::google_callback');

// Everything below requires a logged-in session (AuthMiddleware -> redirects to /login).
$router->group(['middleware' => 'auth'], function ($router) {
    $router->get('/', 'SmartStockController::dashboard');

    // SmartStock corrosion-risk monitoring prototype
    $router->get('/dashboard',  'SmartStockController::dashboard');
    $router->get('/monitoring', 'SmartStockController::monitoring');
    $router->get('/corrosion',  'SmartStockController::corrosion');
    $router->get('/alerts',     'SmartStockController::alerts');
    $router->get('/pos',        'SmartStockController::pos');

    // Hardware inventory – server-rendered, database-backed (see InventoryController / InventoryModel)
    $router->get('/inventory',             'InventoryController::index');
    $router->match('/inventory/create',    'InventoryController::create', ['GET', 'POST']);
    $router->match('/inventory/edit/{id}', 'InventoryController::edit',   ['GET', 'POST']);
    $router->post('/inventory/adjust/{id}', 'InventoryController::adjust');
    $router->post('/inventory/delete/{id}', 'InventoryController::delete');
    $router->get('/inventory/movements/{id}', 'InventoryController::movements');
    $router->get('/inventory/reorder',      'InventoryController::reorder');
    $router->get('/inventory/export',       'InventoryController::export');
    $router->match('/inventory/import',     'InventoryController::import', ['GET', 'POST']);
});

// Product CRUD – protected by 'auth' middleware
$router->group(['prefix' => '/products', 'middleware' => 'auth'], function ($router) {
    $router->get('/', 'ProductController::index');
    $router->match('/create', 'ProductController::create', ['GET', 'POST']);
    $router->match('/edit/{id}', 'ProductController::edit', ['GET', 'POST']);
    $router->get('/delete/{id}', 'ProductController::delete');
});
