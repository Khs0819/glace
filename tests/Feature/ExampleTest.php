<?php

// This backend serves the JSON API and the Filament panel; there is no public
// web UI, so the root path deliberately redirects to the dashboard.
test('the root path redirects to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});
