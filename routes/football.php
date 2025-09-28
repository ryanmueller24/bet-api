<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FootballController;

Route::get('/teams', [FootballController::class, 'getAllTeams']);
Route::get('/teams/{id}', [FootballController::class, 'getTeamById']);
Route::get('/teams/{id}/players', [FootballController::class, 'getTeamRoster']);