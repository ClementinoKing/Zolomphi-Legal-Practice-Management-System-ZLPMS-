<?php

test('redirects the root URL to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login', absolute: false));
});

test('does not expose public registration routes', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
