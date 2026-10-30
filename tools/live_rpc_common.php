<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/vendor/autoload.php';

/** Opt-in live audit harness. The inner Rpc is the actual SDK HTTP transport. */
final class LiveRpcAudit
{
    private const READS=['eth_chainId','eth_blockNumber','eth_getBalance','eth_getTransactionCount','eth_call','eth_gasPrice','eth_getCode','eth_getStorageAt','eth_getLogs','eth_getBlockByNumber','eth_getBlockByHash','eth_getTransactionByHash','eth_getTransactionReceipt','net_version','web3_clientVersion'];
    public array $requests=[];
    public array $checks=[];
    public function __construct(public readonly string $name,private readonly int $budget=250) {}
    public function client(Decimal\Network $network,?string $endpoint=null): Decimal\Client
    {
        $endpoint??=$network->rpcUrl;
        $inner=new Decimal\Rpc($endpoint,timeoutSeconds:15);
        $outer=new Decimal\Rpc($endpoint,function(array $request)use($inner,$network,$endpoint):array{
            $method=$request['method'];
            if(!in_array($method,self::READS,true))throw new LogicException('Live audit blocks non-read RPC: '.$method);
            if(count($this->requests)>=$this->budget)throw new RuntimeException('Live read request budget exceeded');
            if($method==='eth_getLogs'){
                $filter=$request['params'][0]??[];
                if(!isset($filter['address'],$filter['fromBlock'],$filter['toBlock']))throw new LogicException('Live logs require an emitter and bounded blocks');
                $from=Decimal\Units::decimal($filter['fromBlock']);$to=Decimal\Units::decimal($filter['toBlock']);
                if(bccomp($to,$from,0)<0||bccomp(bcsub($to,$from,0),'499',0)>0)throw new LogicException('Live log window exceeds 500 inclusive blocks');
            }
            $record=['chain_id'=>$network->chainId,'endpoint'=>$endpoint,'method'=>$method,'params'=>$request['params'],'checked_utc'=>gmdate('c')];$start=microtime(true);
            try{$result=$inner->call($method,$request['params']);$record['result']=$result;return ['jsonrpc'=>'2.0','id'=>$request['id'],'result'=>$result];}
            catch(Throwable $e){$record['error']=['type'=>get_class($e),'code'=>$e->getCode(),'message'=>$e->getMessage()];if($e instanceof Decimal\RpcException)$record['error']['data']=$e->rpcData;throw $e;}
            finally{$record['elapsed_ms']=(int)round((microtime(true)-$start)*1000);$this->requests[]=$record;}
        });
        return new Decimal\Client(new Decimal\Network($network->chainId,$endpoint,$network->contractCenter),$outer);
    }
    public function check(string $name,Closure $check): mixed
    {
        try{$value=$check();$this->checks[]=['name'=>$name,'status'=>'passed','value'=>$value];return $value;}
        catch(Throwable $e){$this->checks[]=['name'=>$name,'status'=>'failed','error'=>['type'=>get_class($e),'code'=>$e->getCode(),'message'=>$e->getMessage()]];return null;}
    }
    public function note(string $name,string $status,mixed $value): void { $this->checks[]=compact('name','status','value'); }
    public function exitCode(): int { foreach($this->checks as $check)if($check['status']==='failed')return 1;return 0; }
    public function save(string $path): void
    {
        if(!is_dir(dirname($path)))mkdir(dirname($path),0777,true);
        file_put_contents($path,json_encode(['audit'=>$this->name,'sdk_version'=>trim(file_get_contents(dirname(__DIR__).'/VERSION')),'checked_utc'=>gmdate('c'),'policy'=>'read RPC and eth_call only; no estimates, signing, approvals or sends','request_count'=>count($this->requests),'checks'=>$this->checks,'requests'=>$this->requests],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    }
}
