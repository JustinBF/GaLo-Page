<?php

namespace App\Http\Requests;

/**
 * Editar un evento vuelve a repartir sus créditos desde cero, así que las
 * reglas son las mismas que al crearlo.
 */
class UpdateEventRequest extends StoreEventRequest {}
