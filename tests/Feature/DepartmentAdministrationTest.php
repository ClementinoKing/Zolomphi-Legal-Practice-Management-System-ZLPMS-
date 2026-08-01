<?php

test('guests are redirected from department management', function () {
    $this->get(route('administration.departments.index'))
        ->assertRedirect(route('login'));
});
