<?php
namespace app\common\command;

use app\model\WalletAddress;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use Exception;
use think\facade\Log;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39Mnemonic;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Web3p\EthereumUtil\Util;


class GenarateEthAdress extends Command
{
    protected function configure()
    {
        $this->setName('genarateEthAdress')->setDescription('生成以太坊地址');
    }

    protected function execute(Input $input, Output $output)
    {
        for ($i=0; $i < 10000; $i++) { 
            $arr = $this->genarate();
            WalletAddress::insert($arr);
        }   
        return true;
    }

    protected function genarate(){
        //$math = Bitcoin::getMath();
        //$network = Bitcoin::getNetwork();
        $random = new Random();
        // 生成随机数(initial entropy)
        $entropy = $random->bytes(Bip39Mnemonic::MIN_ENTROPY_BYTE_LEN);
        $bip39 = MnemonicFactory::bip39();
        // 通过随机数生成助记词
        $mnemonic = $bip39->entropyToMnemonic($entropy);
        //echo "mnemonic: " . $mnemonic.PHP_EOL.PHP_EOL;// 助记词
        
        $seedGenerator = new Bip39SeedGenerator();
        // 通过助记词生成种子'
        $seed = $seedGenerator->getSeed($mnemonic);
        //echo "seed: " . $seed->getHex() . PHP_EOL;
        $hdFactory = new HierarchicalKeyFactory();
        $master = $hdFactory->fromEntropy($seed);
        
        $util = new Util();
        // 设置路径account
        $hardened = $master->derivePath("44'/60'/0'/0/0");
        // echo " - m/44'/60'/0'/0/0 " .PHP_EOL;
        // echo " public key: " . $hardened->getPublicKey()->getHex().PHP_EOL;
        // echo " private key: " . $hardened->getPrivateKey()->getHex().PHP_EOL;// 可以导入到imtoken使用的私钥
        // echo " address: " . $util->publicKeyToAddress($util->privateKeyToPublicKey($hardened->getPrivateKey()->getHex())) . PHP_EOL;// 私钥导入imtoken后一样的地址
        $arr = [
            'mnemonic' => $mnemonic,
            //'seed' => $seed->getHex(),
            'public_key' => $hardened->getPublicKey()->getHex(),
            'private_key' => $hardened->getPrivateKey()->getHex(),
            'path'=> "44'/60'/0'/0/0",
            'address' => $util->publicKeyToAddress($util->privateKeyToPublicKey($hardened->getPrivateKey()->getHex())),
            'create_time' => time(),
        ];
        return $arr;
    }
}
