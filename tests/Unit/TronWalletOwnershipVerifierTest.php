<?php
namespace Tests\Unit;
use App\Services\TronWalletOwnershipVerifier;
use PHPUnit\Framework\TestCase;
final class TronWalletOwnershipVerifierTest extends TestCase {
    public function test_invalid_inputs(): void {
        $v = new TronWalletOwnershipVerifier();
        $a = 'T9yD14Nj9j7xAB4dbGeiX9h8unkKHxuWwb';
        $s = str_repeat('0', 130);
        $this->assertFalse($v->verify('BEP20',$a,'hello',$s));
        $this->assertFalse($v->verify('TRC20','invalid','hello',$s));
        $this->assertFalse($v->verify('TRC20',$a,'',$s));
        $this->assertFalse($v->verify('TRC20',$a,'hello',$s));
        $this->assertFalse($v->verify('TRC20',$a,'hello','invalid'));
        $this->assertFalse($v->verify('TRC20',$a,str_repeat('x',4097),$s));
        $this->assertFalse($v->verify('TRC20',$a,'hello',str_repeat('f',130)));
    }
}
