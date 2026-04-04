<?php
namespace Core\Models\Cache;
use \InvalidArgumentException;
use Core\Models\Session;
class Cache {
    protected bool      $isSessionCache;              // true: cache lưu vào session, false: lưu cache vào file
    protected ?string   $strSessionKey;
    protected ?string   $strCacheFile;
    protected int      $iCacheTtl; // cache time to live = thời gian tồn tại của cache
    // danh sách file ảnh hưởng tới cache để theo dõi
    protected ?array $arrCacheDependencyFiles ; 
    protected ?string $strFQCN;                      // fully qualified class name

    function __construct(
            bool $isSessionCache = true, 
            ?string $strSessionKey = null, 
            ?string $strCacheFile = null,
            int $iCacheTtl = 3600,
            ?array $arrCacheDependencyFiles = null,
            ?string $strFQCN = null,
            ){
        $this->isSessionCache = $isSessionCache;
        if($this->isSessionCache && ($strSessionKey === null || $strSessionKey === '')){
            throw new InvalidArgumentException('strSessionKey không được là null hoặc blank');
        }
        
        if(!$this->isSessionCache && ($strCacheFile === null || $strCacheFile === '')){
            throw new InvalidArgumentException('strCacheFile không được là null hoặc blank');
        }
        
        if($this->isSessionCache){
            $this->strSessionKey = $strSessionKey;
            $this->strCacheFile = null;
            
        }
        else{
            $this->strSessionKey = null;
            $this->strCacheFile = $strCacheFile;
            
        }
        if($iCacheTtl < MIN_CACHE_TTL){
            throw new InvalidArgumentException("Time to live của cache tối thiểu là ".MIN_CACHE_TTL." seconds");
        }
        $this->iCacheTtl = $iCacheTtl;
        $this->arrCacheDependencyFiles = $arrCacheDependencyFiles;
        $this->strFQCN = $strFQCN;
    }	
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function isDiskCacheExpired(): bool {
        if (!file_exists($this->strCacheFile)) {
            return true;
        }
        $cacheMtime = filemtime($this->strCacheFile);//thời điểm modification 
        if($cacheMtime && (time() - $cacheMtime > $this->iCacheTtl)){//đã quá thời gian tồn tại của cache
            return true;
        }
        if($this->arrCacheDependencyFiles !== null){
            foreach ($this->arrCacheDependencyFiles as $file) {
                if (file_exists($file) && filemtime($file) > $cacheMtime) {
                    return true; // xuất hiện file mới hơn cache file
                }
            }
        }
        
        return false;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function isSessionCacheExpired(): bool {
        $arrCache = Session::get($this->strSessionKey);
        if($arrCache === null){
            return true;
        }
        $cacheMtime = $arrCache['created_at'] ?? 0; //0 = 1970-01-01 00:00:00
        if($cacheMtime > 0 && (time() - $cacheMtime > $this->iCacheTtl)){//đã quá thời gian tồn tại của cache
            return true;
        }
        if($this->arrCacheDependencyFiles !== null){
            foreach ($this->arrCacheDependencyFiles as $file) {
                if (file_exists($file) && filemtime($file) > $cacheMtime) {
                    return true; // xuất hiện file mới hơn cache file
                }
            }
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function isCacheValid(): bool {
        if($this->isSessionCache){
            return !$this->isSessionCacheExpired();
        }
        else{
            return !$this->isDiskCacheExpired();
        }
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function saveDiskCache(object $obj): bool {
        $serialized = serialize($obj);
        return (bool)file_put_contents($this->strCacheFile, $serialized, LOCK_EX);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function saveSessionCache(object $obj): bool {
        $serialized = serialize($obj);
        Session::set($this->strSessionKey, ['data' => $serialized, 'created_at' => time()]);
        return true; //không bị exception thì return true. return true/false cho tương thích với saveDiskCache
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function saveCache(object $obj): bool {
        if($this->isSessionCache){
            return $this->saveSessionCache($obj);
        }
        else{
            return $this->saveDiskCache($obj);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function loadDiskCache(): ?object {
        if (!$this->isCacheValid()) {
            return null;
        }
        $cache = file_get_contents($this->strCacheFile);
        if ($cache === false){ //file
            return null;
        }
        try{
            $options = $this->strFQCN 
            ? ['allowed_classes' => [$this->strFQCN]] //bắt buộc tạo $obj theo class đã định nghĩa
            : ['allowed_classes' => false]; //chỉ cho phép tạo dữ liệu dạng scalar/array, không cho phép bất kỳ class nào
            $obj = unserialize($cache,$options);//file     
        }
        catch (\Throwable $e){
            return null;
        }
        if ($this->strFQCN && !$obj instanceof $this->strFQCN) {
            return null;
        }
        return $obj;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function loadSessionCache(): ?object {
        if (!$this->isCacheValid()) {
            return null;
        }
        $cache = Session::get($this->strSessionKey);
        if ($cache === null) {
            return null;
        }
        try{
            $options = $this->strFQCN 
            ? ['allowed_classes' => [$this->strFQCN]] //bắt buộc tạo $obj theo class đã định nghĩa
            : ['allowed_classes' => false]; //chỉ cho phép tạo dữ liệu dạng scalar/array, không cho phép bất kỳ class nào
            $obj = unserialize($cache['data'],$options);//session
            
        }
        catch (\Throwable $e){
            return null;
        }
        if ($this->strFQCN && !$obj instanceof $this->strFQCN) {
            return null;
        }
        return $obj;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function loadCache(): ?object {
        if($this->isSessionCache){
            return $this->loadSessionCache();
        }
        else{
            return $this->loadDiskCache();
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function clearCache(): bool {
        if($this->isSessionCache){
            Session::remove($this->strSessionKey);
            return true; // nếu không throw exception thì return true
        }
        //từ đây trở đi $this->isSessionCache = false nghĩa là disk cache
        if (file_exists($this->strCacheFile)){ 
            return unlink($this->strCacheFile);
        }    
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function refreshCache(object $obj): bool {
        $this->clearCache();
        return $this->saveCache($obj);
    }
}