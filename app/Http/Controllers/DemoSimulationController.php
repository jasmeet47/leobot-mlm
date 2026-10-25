<?php
namespace App\Http\Controllers;
use App\Services\DemoSimulationService as Sim;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
final class DemoSimulationController extends Controller {
 public function index(Request $r){Sim::guard();$id=(int)$r->user()->id;return view('demo.simulation',['balance'=>Sim::balance($id),'entries'=>DB::table('demo_sim_ledger')->where('user_id',$id)->orderByDesc('id')->limit(20)->get()]);}
 public function debit(Request $r){Sim::guard();$data=$r->validate(['amount'=>'required|numeric|gt:0|lte:1000','reference'=>'required|string|min:12|max:100|regex:/^[a-zA-Z0-9_-]+$/']);Sim::transfer((int)$r->user()->id,'debit',(string)$data['amount'],$data['reference']);return redirect()->route('demo.simulation.index')->with('status','Simulated debit recorded.');}
 public function credit(Request $r){Sim::guard();$data=$r->validate(['user_id'=>'required|integer|exists:users,id','amount'=>'required|numeric|gt:0|lte:1000','reference'=>'required|string|min:12|max:100|regex:/^[a-zA-Z0-9_-]+$/']);Sim::transfer((int)$data['user_id'],'credit',(string)$data['amount'],$data['reference']);return redirect()->route('demo.simulation.index')->with('status','Simulated credit recorded.');}
}
