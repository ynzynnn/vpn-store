<?php

namespace App\Http\Controllers;

use App\Models\AllocatedPort;
use App\Models\Server;
use App\Models\VpnAccount;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $servers = Server::where('is_active', true)->withCount(['vpnAccounts', 'allocatedPorts'])->get();
        $totalServers = $servers->count();
        $totalActivePorts = AllocatedPort::where('status', 'active')->count();
        $totalAccounts = VpnAccount::where('status', 'active')->count();

        return view('welcome', compact('servers', 'totalServers', 'totalActivePorts', 'totalAccounts'));
    }
}
