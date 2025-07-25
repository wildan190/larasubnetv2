<?php

namespace App\Http\Controllers;

use App\Services\MidtransNotificationHandler;
use Illuminate\Http\Request;

class MidtransCallbackController extends Controller
{
    public function handle(Request $request)
    {
        MidtransNotificationHandler::handle($request->all());

        return response()->json(['message' => 'Callback processed']);
    }
}
