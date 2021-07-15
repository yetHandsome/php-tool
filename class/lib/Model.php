<?php

namespace lib;

use  lib\DbConnect;

class Model{
    private $showSql = true;
    private $conn;
    private $table;
    private $sql;
    private $whereBindParam=array();
    private $options;
    private $params;
    private $name;
    private $break_reconnect = true;
    //    $params = [
//        'type' => 'mysql',
//        'hostname' => "127.0.0.1",
//        'hostport' => "3306",
//        'database' => "db1",
//        'username' => "username",
//        'password' => "password",
//        'charset' => "utf8",
//    ]
    public function __construct($params,$name = '') {
        $this->params = $params;
        $this->name = $name;
        $conn = DbConnect::getInstace($params,$name);
        $this->conn = $conn;
    }
    
    public function getconn() {
        $reallyConnet = is_null($this->conn) ? true : false;
        if($reallyConnet){
            return DbConnect::getInstace($this->params,$this->name,$reallyConnet);
        }
        return $this->conn;
    }
    
    //在客户端感觉上Model是继承了PDO，其实是一种组合写法
    public function __call($func, $arguments){
        return call_user_func_array(array($this->getconn(),$func),array($arguments));
    }
    
    public function table($table){
        $this->table = $table;
        return $this;
    }
    
    //$multiple 是否为多条插入
    //$useTransaction 是否启用事务
    //单条返回主键id,多条放回成功条数
    public function insert($map, $multiple = false, $useTransaction = false){
        if (!$map || !is_array($map)) {
            return FALSE;
        } else {
            $fields = $values = array();
            
            if($multiple){
                foreach ($map[0] as $key => $value) {
                    $fields[] = '`' . $key . '`';
                    $values[] = ":{$key}";
                }
            }else{
                foreach ($map as $key => $value) {
                    $fields[] = '`' . $key . '`';
                    $values[] = ":{$key}";
                }
            }
            

            $fieldString = implode(',', $fields);
            $valueString = implode(',', $values);

            $this->sql = 'INSERT INTO ' . $this->table . " ($fieldString) VALUES ($valueString)";
            
            try {
                if($useTransaction){
                    $this->beginTransaction();
                }
                
                $stmt = @$this->getconn()->prepare($this->sql);
                if(!$stmt){
                    $this->reportError();
                }

                if($multiple){
                    $successCount = 0;
                    foreach ($map as $v) {
                        foreach ($v as $key => $value) {
                            $bindParamKey = "__bindParam_".$key;
                            ${$bindParamKey} = $value;
                            $stmt->bindParam(":{$key}", ${$bindParamKey});
                        }
                        $status = $stmt->execute();
                        if(!$status){
                            $this->rollBack();
                            return false;
                        }
                        $successCount++;
                    }
                    if($useTransaction){
                        $this->commit();
                    }
                    $this->clear();
                    return $successCount;
                }else{
                    foreach ($map as $key => $value) {
                        $bindParamKey = "__bindParam_".$key;
                        ${$bindParamKey} = $value;
                        $stmt->bindParam(":{$key}", ${$bindParamKey});
                        //$stmt->bindValue(":{$key}", $value);
                    }
                    if ($this->run($stmt)) {
                        return $this->getconn()->lastInsertId();
                    }
                }
                
            } catch (\PDOException $e) {
                if($useTransaction){
                    $this->rollBack();
                }
                if ($this->isBreak($e)) {
                    return $this->close()->insert($map);
                }
                throw new PDOException($e, $this->params, $this->getLastsql());
            } catch (\Throwable $e) {
                if($useTransaction){
                    $this->rollBack();
                }
                if ($this->isBreak($e)) {
                    return $this->close()->insert($map);
                }
                throw $e;
            } catch (\Exception $e) {
                if($useTransaction){
                    $this->rollBack();
                }
                if ($this->isBreak($e)) {
                    return $this->close()->insert($map);
                }
                throw $e;
            }
           
            return false;
        }
    }
    
    public function replace($map){
        if (!$map || !is_array($map)) {
            return FALSE;
        } else {
            $fields = $values = array();

            foreach ($map as $key => $value) {
                $fields[] = '`' . $key . '`';
                $values[] = ":{$key}";
            }

            $fieldString = implode(',', $fields);
            $valueString = implode(',', $values);

            $this->sql = 'REPLACE INTO ' . $this->table . " ($fieldString) VALUES ($valueString)";
            
            try {
                $stmt = @$this->getconn()->prepare($this->sql);
                if(!$stmt){
                    $this->reportError();
                }
                foreach ($map as $key => $value) {
                    $bindParamKey = "__bindParam_".$key;
                    ${$bindParamKey} = $value;
                    $stmt->bindParam(":{$key}", ${$bindParamKey});
                }
                if ($this->run($stmt)) {
                    return @$this->getconn()->lastInsertId();
                }
            } catch (\PDOException $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->replace($map);
                }
                throw new PDOException($e, $this->params, $this->getSql());
            } catch (\Throwable $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->replace($map);
                }
                throw $e;
            } catch (\Exception $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->replace($map);
                }
                throw $e;
            }
            
            return false;
        }
    }
    
    public function getInsertId($name=''){
        return $this->getconn()->lastInsertId($name);
    } 
    
    /**
     * Field
     */
    final public function field($field) {
        if (!$field) {
            return $this;
        }

        $str = '';
        if (is_array($field)) {
            foreach ($field as $val) {
                $str .= '`' . $val . '`, ';
            }

            $this->options['field'] = substr($str, 0, strlen($str) - 2); // 2:　Cos there is a BLANK
        } else {
            $this->options['field'] = $field;
        }

        unset($str, $field);
        return $this;
    }
    
    public function find(){
        $res = $this->select();
        $r = is_array($res) ? $res[0] : $res;
        return $r;
    }

    
    public function select(){
        echo 'select_start:'.PHP_EOL;
        if (isset($this->options['field'])) {
            $field = $this->options['field'];
        } else {
            $field = '*';
        }

        $this->sql = 'SELECT ' . $field . ' FROM `' . $this->table . '`';
        
        if(isset($this->options['where'])){
            $this->sql.= ' WHERE ' . $this->options['where'];
        }
        
        if (isset($this->options['group'])) {
            $this->sql .= ' GROUP BY ' . $this->options['group'];
            if (isset($this->options['having'])) {
                $this->sql .= ' HAVING ' . $this->options['having'];
            }
        }
        
        if (isset($this->options['order'])) {
            $this->sql .= ' ORDER BY ' . $this->options['order'];
        }
        
        if (isset($this->options['limit'])) {
            $this->sql .= ' LIMIT ' . $this->options['limit'];
        }

        try {
            $stmt = @$this->getconn()->prepare($this->sql);
            if(!$stmt){
                $this->reportError();
            }
            if ($this->run($stmt,$this->whereBindParam)){
                return $result=$stmt->fetchAll(\PDO::FETCH_ASSOC); 
            }
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->select();
            }
            echo 'throw new PDOException'.PHP_EOL;
            throw new PDOException($e, $this->params, $this->getSql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->select();
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->select();
            }
            throw $e;
        }
        echo 'select_return:'.PHP_EOL;
        
        return false;

    }
    
    public function update($map, $update_all = false){
        //如果是一个没有条件的跟新，那么必须指定这个是全部跟新
        if (!$this->options['where'] && $update_all) {
            return FALSE;
        }

        if (!$map) {
            return FALSE;
        } else {
            $this->sql = 'UPDATE `' . $this->table . '` SET ';
            $sets = array();
            $sets_value = array();
            
            foreach ($map as $key => $value) {
                if (strpos($key, '+') !== FALSE) {
                    list($key, $flag) = explode('+', $key);
                    $sets[] = "`$key` = `$key` + ?";
                } elseif (strpos($key, '-') !== FALSE) {
                    list($key, $flag) = explode('-', $key);
                    $sets[] = "`$key` = `$key` - ?";
                } else {
                    $sets[] = "`$key` = ?";
                }
                $sets_value[] = $value;
            }

            $this->sql .= implode(',', $sets) . ' ';

            if(isset($this->options['where'])){
                $this->sql.= ' WHERE ' . $this->options['where'];
            }

            if (isset($this->options['order'])) {
                $this->sql .= ' ORDER BY ' . $this->options['order'];
            }

            if (isset($this->options['limit'])) {
                $this->sql .= ' LIMIT ' . $this->options['limit'];
            }
            try {
                 $stmt = @$this->getconn()->prepare($this->sql);
                if(!$stmt){
                    $this->reportError();
                }
                $this->whereBindParam = array_merge($sets_value,$this->whereBindParam);
                if ($this->run($stmt,$this->whereBindParam)){
                    return $stmt->rowCount() ? $stmt->rowCount() : true;
                }
            } catch (\PDOException $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->update($map, $update_all);
                }
                throw new PDOException($e, $this->params, $this->getSql());
            } catch (\Throwable $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->update($map, $update_all);
                }
                throw $e;
            } catch (\Exception $e) {
                if ($this->isBreak($e)) {
                    return $this->close()->update($map, $update_all);
                }
                throw $e;
            }
           
        
            return false;
        }
    }
    
    public function delete($delall=false){
        //如果是一个没有删除条件的删除，那么必须指定这个是全部删除
        if (!isset($this->options['where']) && $delall) {
            return FALSE;
        }
        
        $this->sql = 'DELETE FROM `' . $this->table . '`';
        
        if(isset($this->options['where'])){
            $this->sql.= ' WHERE ' . $this->options['where'];
        }
        
        if (isset($this->options['order'])) {
            $this->sql .= ' ORDER BY ' . $this->options['order'];
        }
        
        if (isset($this->options['limit'])) {
            if(!is_numeric($this->options['limit'])){
                exit('delete 语句不支持limit '.$this->options['limit'].' 请改成类似limit x');
            }
            $this->sql .= ' LIMIT ' . $this->options['limit'];
        }
        try {
            $stmt = @$this->getconn()->prepare($this->sql);
            if(!$stmt){
                $this->reportError();
            }
            if ($this->run($stmt,$this->whereBindParam)){
                return $stmt->rowCount() ? $stmt->rowCount() : true;
            }
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->delete($delall);
            }
            throw new PDOException($e, $this->params, $this->getSql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->delete($delall);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
               return $this->close()->delete($delall);
            }
            throw $e;
        }
        
        
        return false;
    }
    
    //这里特意不用query，避免跟PDO的query同名
    public function runSql($sql,$bind_paramlist = array(),$type = 'DQL',$type2='INSERT'){
        $this->sql = $sql;
        $this->whereBindParam = $bind_paramlist;
        
        try {
            $stmt = @$this->getconn()->prepare($this->sql);
            if(!$stmt){
                $this->reportError();
            }

            //DQL  SELECT
            if($type == 'DQL'){
                $this->run($stmt,$this->whereBindParam);
                return $result=$stmt->fetchAll(\PDO::FETCH_ASSOC); 

            //DML  INSERT UPDATE DELETE
            }elseif($type == 'DML'){
                if ($this->run($stmt,$this->whereBindParam)){
                    if ($type2 == 'INSERT') {
                        $this->getLastInsertId = $pdo->lastInsertId();
                        return $this->getLastInsertId;
                    } else {
                        return $stmt->rowCount();
                    }
                } 

                return false;

            //DDL CREATE TABLE/VIEW/INDEX/SYN/CLUSTER
            }elseif($type == 'DDL'){
                return $this->run($stmt,$this->whereBindParam);

            //DCL GRANT ROLLBACK COMMIT
            }else{
                return $this->run($stmt,$this->whereBindParam);
            }
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->runSql($sql, $bind_paramlist, $type, $type2);
            }
            throw new PDOException($e, $this->params, $this->getSql());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->runSql($sql, $bind_paramlist, $type, $type2);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->runSql($sql, $bind_paramlist, $type, $type2);
            }
            throw $e;
        }
    }
    
    public function run($stmt,$BindParam = array()){
        $this->clear();
        if(!empty($BindParam)){
            return $stmt->execute($BindParam);
        }else{
            return $stmt->execute();
        }
    }
    
    public function clear(){
        $this->table            = null;
        $this->options          = null;
        $this->whereBindParam   = array();
    }
    
    public function getSql() {
        return $this->sql;
    }
    
    /*
     * $sth = $dbh->prepare('SELECT name, colour, calories
            FROM fruit
            WHERE calories < ? AND colour = ?');
        $sth->execute($this->whereBindParam);
     */
    //提供一个直接写where 的方法以便一些复杂条件，使用这个更方便
    public function whereStr($str,$whereBindParam_list = array()){
        $this->options['where'] = $str;
        $this->whereBindParam   = $whereBindParam_list;
        return $this;
    }
    
    public function where($where,$flag = 'AND'){
        list($where,$whereBindParam) = $this->getSateWhere($where);
        if(isset($this->options['where'])){
            $this->options['where'] .= ' '.$flag.' '.$where;
        }else{
            $this->options['where'] = $where;
        }
        
        if($whereBindParam){
            foreach ($whereBindParam as $key => $value) {
                array_push( $this->whereBindParam,$value);
            }
        }
        
        return $this;
    }
    
    public function getSateWhere($where){
        $where_str = '';
        $whereBindParam = [];
        if(!empty($where)){
            if(is_array($where)){
                
                foreach ($where as $key => $value){
                    $sign = is_array($value) ? $value[0] : '=';
                    is_array($value) && $value = $value[1];
                    if(is_array($value)){
                        $flag = '  (' . rtrim( str_pad('?', 2 * count($value), ',?') , ',') .')';
                        if(strtolower($sign) == 'between'){
                            $flag =  '? and ?' ;
                        }
                        foreach ($value as $k2 => $v2) {
                            $whereBindParam[] = $v2;
                        }
                    }else{
                        $flag = '?';
                        $whereBindParam[] = $value;
                    }
                    $whereArr[] = " `$key` $sign $flag";
                }
                
                if(strtolower($sign) == 'between'){
                    $where_str  .=   implode(' and ', $whereArr) ;
                }else{
                    $where_str  .=  "(" . implode(' and ', $whereArr) . ")";
                }
                
            }else{
                $where_str .= $where;
            }
        }
        
        return [$where_str,$whereBindParam];
    }
    
    public function groupBy($str){
        $this->options['group'] = $str;
        return $this;
    }
    
    public function orderBy($str){
        $this->options['order'] = $str;
        return $this;
    }
    
    private function reportError(){
        if($this->showSql){
            exit('预编译失败请检查sql: '.$this->sql.' 是否有误');
        }
        exit('预编译失败请检查sql是否有误');
    }




    public function having($str){
        $this->options['having'] = $str;
        return $this;
    }
    
    public function limit($str){
        $this->options['limit'] = $str;
        return $this;
    }
    
    public function beginTransaction(){
        $this->getconn()->beginTransaction();
    }
    
    public function commit(){
        $this->getconn()->commit();
    }
    
    public function rollBack(){
        $this->getconn()->rollBack();
    }
    
    /**
     * 是否断线
     * @access protected
     * @param \PDOException|\Exception  $e 异常对象
     * @return bool
     */
    protected function isBreak($e)
    {
        //是否重连判断
        if (!$this->break_reconnect) {
            return false;
        }

        $info = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'failed with errno',
        ];

        $error = $e->getMessage();

        foreach ($info as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 关闭数据库（或者重新连接）
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->conn    = null;
        return $this;
    }
    

}
