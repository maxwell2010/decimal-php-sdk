<?php
declare(strict_types=1);
$parityCatalog=json_decode(file_get_contents(dirname(__DIR__).'/docs/audit-operation-parity.json'),true,512,JSON_THROW_ON_ERROR);
same(count($parityCatalog['operations']),55);$parityNames=[];$parityDisabled=[];
foreach($parityCatalog['operations'] as $entry){
    $parityNames[]=$entry['python_operation'];
    same(in_array($entry['status'],['implemented-workflow','disabled-unsafe-reference'],true),true);
    if($entry['status']==='disabled-unsafe-reference')$parityDisabled[]=$entry['python_operation'];
    foreach($entry['php_helpers'] as $helper){[$class,$method]=explode('::',$helper);same(is_callable(['Decimal\\'.$class,$method])||method_exists('Decimal\\'.$class,$method),true);}
    same(is_file(dirname(__DIR__).'/'.$entry['implementation_evidence']['manifest']),true);
    foreach($entry['implementation_evidence']['tests'] as $file)same(is_file(dirname(__DIR__).'/'.$file),true);
}
same(count(array_unique($parityNames)),55);same($parityDisabled,['decimal.multisend_erc20']);
same(trim(file_get_contents(dirname(__DIR__).'/VERSION')),$parityCatalog['release']);
foreach($parityCatalog['additional_current_operations']??[] as $entry){[$class,$method]=explode('::',$entry['php_helper']);same(method_exists('Decimal\\'.$class,$method),true);}
echo "Complete operation catalog and linked implementation evidence verified\n";
