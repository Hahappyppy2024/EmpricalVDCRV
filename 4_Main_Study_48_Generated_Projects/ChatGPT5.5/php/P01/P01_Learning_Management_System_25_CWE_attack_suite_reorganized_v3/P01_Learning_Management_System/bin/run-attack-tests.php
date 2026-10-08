<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/vendor/autoload.php';
require $root.'/tests/SecurityAttack/AttackTestCase.php';

use Tests\SecurityAttack\AttackTestCase;

$files=glob($root.'/exploit/SecurityAttack/CWE_*_AttackTest.php');sort($files);
$counts=[];$results=[];
$nativeNa=['120','121','122','125','416','476','787'];
foreach($files as $file){
    $case=null;
    preg_match('/CWE_(\d+)/',basename($file),$num);
    $num=str_pad((string)($num[1]??''),3,'0',STR_PAD_LEFT);
    try{
        if(in_array((string)(int)$num,$nativeNa,true)){
            $labels=['120'=>'Classic Buffer Overflow','121'=>'Stack-based Buffer Overflow','122'=>'Heap-based Buffer Overflow','125'=>'Out-of-bounds Read','416'=>'Use After Free','476'=>'NULL Pointer Dereference','787'=>'Out-of-bounds Write'];
            $result=AttackTestCase::result('CWE-'.(int)$num,$labels[(string)(int)$num]??basename($file),'NOT_APPLICABLE','P01 is PHP application code and exposes no application-layer native-memory primitive for this CWE.');
        }else{
            $fn=require $file;
            if(!is_callable($fn))throw new RuntimeException('Test file must return a callable.');
            $case=new AttackTestCase($root);
            $result=$fn($case);
        }
    }catch(Throwable $e){
        if(str_starts_with($e->getMessage(),'SKIP_ENV:')){
            preg_match('/CWE_(\d+)/',basename($file),$m);$cwe='CWE-'.(int)($m[1]??0);
            $result=AttackTestCase::result($cwe,basename($file),'SKIPPED_ENV',$e->getMessage());
        }else{
            preg_match('/CWE_(\d+)/',basename($file),$m);$cwe='CWE-'.(int)($m[1]??0);
            $result=AttackTestCase::result($cwe,basename($file),'ERROR',$e::class.': '.$e->getMessage());
        }
    }finally{if($case instanceof AttackTestCase)$case->close();}
    $results[]=$result;$counts[$result['status']]=($counts[$result['status']]??0)+1;
    printf("%-8s %-16s %s\n",$result['cwe'],$result['status'],$result['evidence']);
}
echo "\nSummary\n";foreach($counts as $k=>$v)echo "  {$k}: {$v}\n";
file_put_contents($root.'/attack-test-results.json',json_encode(['generated_at'=>gmdate('c'),'results'=>$results,'summary'=>$counts],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "\nMachine-readable results: attack-test-results.json\n";
