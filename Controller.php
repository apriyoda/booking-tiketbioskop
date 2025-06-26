<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController; // PENTING: Tambahkan ini

class Controller extends BaseController // PENTING: Ubah ini menjadi extends BaseController
{
    use AuthorizesRequests, ValidatesRequests; // PENTING: Tambahkan trait ini
}
