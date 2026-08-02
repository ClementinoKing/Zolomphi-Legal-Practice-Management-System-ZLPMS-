<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Route::get('/_test-errors/400', fn (): never => abort(400, 'sensitive bad request details'));
    Route::get('/_test-errors/401', fn (): never => abort(401, 'sensitive authentication details'));
    Route::get('/_test-errors/403', fn (): never => abort(403, 'sensitive authorization details'));
    Route::get('/_test-errors/419', fn (): never => abort(419, 'sensitive session details'));
    Route::get('/_test-errors/429', fn (): never => abort(429, 'sensitive throttling details'));
    Route::get('/_test-errors/500', function (): never {
        throw new RuntimeException('sensitive server details');
    });
    Route::get('/_test-errors/503', fn (): never => abort(503, 'sensitive maintenance details'));
    Route::get('/_test-errors/422', function (): never {
        throw ValidationException::withMessages(['field' => 'The field is invalid.']);
    });
});

test('direct browser errors use the branded page and preserve status codes', function () {
    $this->get('/_test-errors/400')
        ->assertStatus(400)
        ->assertSee('errors\\/error');

    $this->get('/_test-errors/401')
        ->assertUnauthorized()
        ->assertSee('errors\\/error');

    $this->get('/_test-errors/403')
        ->assertForbidden()
        ->assertSee('errors\\/error')
        ->assertDontSee('sensitive authorization details');

    $this->get('/missing-error-page')
        ->assertNotFound()
        ->assertSee('errors\\/error');

    $this->get('/_test-errors/500')
        ->assertInternalServerError()
        ->assertSee('errors\\/error')
        ->assertDontSee('sensitive server details');

    $this->get('/_test-errors/503')
        ->assertStatus(503)
        ->assertSee('errors\\/error')
        ->assertDontSee('sensitive maintenance details');
});

test('inertia errors render the branded error component with a successful page response', function () {
    $this->withHeaders([
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/_test-errors/403')
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/error')
        ->assertJsonPath('props.status', 403);
});

test('json errors remain json responses', function () {
    $this->withHeaders(['Accept' => 'application/json'])
        ->get('/_test-errors/403')
        ->assertForbidden()
        ->assertJsonStructure(['message'])
        ->assertJsonMissing(['component']);
});

test('validation errors are not replaced with a branded error page', function () {
    $this->withHeaders(['Accept' => 'application/json'])
        ->get('/_test-errors/422')
        ->assertStatus(422)
        ->assertJsonValidationErrors('field');
});

test('session and throttling errors use their dedicated status content', function () {
    $this->get('/_test-errors/419')
        ->assertStatus(419)
        ->assertSee('errors\\/error');

    $this->get('/_test-errors/429')
        ->assertTooManyRequests()
        ->assertSee('errors\\/error');
});
