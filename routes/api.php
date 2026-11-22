<?php

use App\Http\Controllers\McpController;
use App\Http\Middleware\AuthenticateMcp;
use Illuminate\Support\Facades\Route;

// The MCP endpoint. Point any MCP client (Claude Desktop, Cursor, MCP Inspector) here.
Route::post('/mcp', McpController::class)->middleware([AuthenticateMcp::class, 'throttle:120,1'])->name('mcp');
