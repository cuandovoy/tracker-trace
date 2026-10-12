<?php

return [
    'required' => 'El campo :attribute es obligatorio.', 'required_if' => 'Completá :attribute para esta operación.',
    'email' => 'Ingresá un correo válido.', 'unique' => 'Este :attribute ya está registrado.', 'exists' => 'La selección de :attribute no está disponible.',
    'string' => 'El campo :attribute debe ser texto.', 'numeric' => 'Ingresá un número válido en :attribute.', 'integer' => 'Ingresá un número entero en :attribute.',
    'boolean' => 'La opción :attribute no es válida.', 'date_format' => 'Ingresá una fecha válida en :attribute.', 'timezone' => 'Seleccioná una zona horaria válida.',
    'in' => 'La opción de :attribute no es válida.', 'confirmed' => 'Las contraseñas no coinciden.',
    'min' => ['numeric' => ':attribute debe ser al menos :min.', 'string' => ':attribute debe tener al menos :min caracteres.'],
    'max' => ['numeric' => ':attribute no puede superar :max.', 'string' => ':attribute no puede superar :max caracteres.'],
    'before_or_equal' => ':attribute no puede ser posterior a :date.', 'after_or_equal' => ':attribute no puede ser anterior a :date.',
    'password' => ['letters' => 'La contraseña debe incluir letras.', 'numbers' => 'La contraseña debe incluir números.'],
    'attributes' => ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'plate' => 'matrícula', 'brand' => 'marca', 'model' => 'modelo', 'year' => 'año', 'capacity_tons' => 'capacidad', 'odometer' => 'odómetro', 'truck_id' => 'camión', 'driver_id' => 'chofer', 'date' => 'fecha', 'origin' => 'origen', 'destination' => 'destino', 'cargo' => 'carga', 'tons' => 'toneladas', 'planned_km' => 'distancia prevista', 'actual_km' => 'distancia realizada', 'hired_on' => 'fecha de ingreso', 'license' => 'licencia', 'category' => 'categoría', 'from' => 'desde', 'to' => 'hasta', 'next_service' => 'próximo servicio', 'last_service' => 'último servicio', 'cancellation_reason' => 'motivo de anulación', 'admin_email' => 'correo del administrador', 'admin_name' => 'nombre del administrador'],
];
