<?php

it('el laboratorio de la mesa no existe fuera del entorno local', function () {
    // La ruta se registra solo si app()->isLocal(); los tests corren en "testing".
    $this->get('/laboratorio-mesa')->assertNotFound();
});
