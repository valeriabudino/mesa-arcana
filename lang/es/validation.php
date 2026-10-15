<?php

/*
| Mensajes de validación en español (las reglas que usa Mesa Arcana).
| Lo que falte cae en el inglés de Laravel.
*/

return [
    'after_or_equal' => ':Attribute tiene que ser posterior o igual a :date.',
    'before_or_equal' => ':Attribute no puede ser posterior a :date.',
    'boolean' => ':Attribute tiene que ser sí o no.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date_format' => ':Attribute no tiene el formato correcto.',
    'email' => ':Attribute tiene que ser un email válido.',
    'enum' => ':Attribute no es una opción válida.',
    'exists' => ':Attribute no es válida.',
    'in' => ':Attribute no es una opción válida.',
    'integer' => ':Attribute tiene que ser un número entero.',
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => ':Attribute tiene que ser al menos :min.',
        'string' => ':Attribute tiene que tener al menos :min caracteres.',
    ],
    'required' => 'Falta :attribute.',
    'string' => ':Attribute tiene que ser un texto.',
    'unique' => 'Ya existe alguien con ese :attribute.',

    'attributes' => [
        'nombre' => 'tu nombre',
        'motivo' => 'el motivo',
        'contenido' => 'el mensaje',
        'servicio' => 'el servicio',
        'pregunta' => 'la pregunta',
        'indice_abanico' => 'la carta',
        'posicion' => 'la posición',
        'fecha' => 'la fecha',
        'hora' => 'la hora',
        'ciudad_id' => 'la ciudad',
        'planeta' => 'el punto',
        'email' => 'email',
        'password' => 'la contraseña',
        'disponible' => 'disponible',
    ],
];
