<?php

//require_once('phppgsql.php');

class apitest extends apiBaseClass {

	// Получить счета клиента
	// http://localhost/projects/api_test/?apitest.getAccounts={"Customer":"1"}
	function getAccounts($apiMethodParams) {
        
		$retJSON = $this->createDefaultJson();
			
        if (isset($apiMethodParams->Customer)){
            //Все ок параметры верные, их и вернем
            			
			$result = MySQLiWorker::get_data_from_pgsql('select * from tb_accounts where customer_id = ' . $apiMethodParams->Customer);
				
			$retJSON->Accounts = array();
	
			while ($row = pg_fetch_row($result)) {
				
				array_push(
					$retJSON->Accounts, array(
						'acc_id' => $row[0],
						'saldo_nd' => $row[1],
						'limit_nd' => $row[2],
						'cur_id' => $row[3],
						'name_v' => $row[5],
						'num_v' => $row[6]
					)
				);
				
			}
			
        }else{
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        return $retJSON;
    }
	
	// Получить курсы валют
	//http://localhost/projects/api_test/?apitest.getCourses
	function getCourses($apiMethodParams) {
		
		$retJSON = $this->createDefaultJson();
		
		$retJSON->Currencyes = array( 
			array ( 'cur' => 'KGS', 'course_sell' => 1, 'course_buy' => 1),
			array ( 'cur' => 'USD', 'course_sell' => 68.8, 'course_buy' => 68.2),
			array ( 'cur' => 'RUB', 'course_sell' => 1.15, 'course_buy' => 1.12),
			array ( 'cur' => 'EUR', 'course_sell' => 75.7, 'course_buy' => 74.2),
			array ( 'cur' => 'KZT', 'course_sell' => 0.23, 'course_buy' => 0.21)
		);
		
		return $retJSON;
	}
	
	// Заблокировать сумму на счете
	// http://localhost/projects/api_test/?apitest.lockAccount={"Acc":"1", "Sum":"100"}
	function lockAccount($apiMethodParams) {
        
		$retJSON = $this->createDefaultJson();
			
        if (isset($apiMethodParams->Acc) && isset($apiMethodParams->Sum)){
            			
			$result = MySQLiWorker::get_data_from_pgsql('select sum_lock (' . $apiMethodParams->Acc . ', ' . $apiMethodParams->Sum . ', 1)');
	
			$retJSON->locked = 'successful';
	
        }else{
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        return $retJSON;
    }
	
	// Pаблокировать сумму на счете
	// http://localhost/projects/api_test/?apitest.unlockAccount={"Acc":"1", "Sum":"100"}
	function unlockAccount($apiMethodParams) {
        
		$retJSON = $this->createDefaultJson();
			
        if (isset($apiMethodParams->Acc) && isset($apiMethodParams->Sum)){
            			
			$result = MySQLiWorker::get_data_from_pgsql('select sum_lock (' . $apiMethodParams->Acc . ', ' . $apiMethodParams->Sum . ', 0)');
	
			$retJSON->unlocked = 'successful';
	
        }else{
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        return $retJSON;
    }
	
	// Создать сделку
	// http://localhost/projects/api_test/?apitest.createDeal={"Deal":"1","SellSum":"100","SellCur":"1","SellAccDt":"1","SellAccCt:"2","BuySum":"100","BuyCur":"1","BuyAccDt":"1","BuyAccCt:"2"}
	function createDeal($apiMethodParams) {
        
		$retJSON = $this->createDefaultJson();
		
		
        if (
			isset($apiMethodParams->Deal) 
			
			&& isset($apiMethodParams->SellSum) && isset($apiMethodParams->SellCur) 
			&& isset($apiMethodParams->SellAccDt) && isset($apiMethodParams->SellAccCt)
			
			&& isset($apiMethodParams->BuySum) && isset($apiMethodParams->BuyCur) 
			&& isset($apiMethodParams->BuyAccDt) && isset($apiMethodParams->BuyAccCt)
			
		){
            			
			$result = MySQLiWorker::get_data_from_pgsql('select execute_deal(' .
				$apiMethodParams->Deal . ', ' .
				$apiMethodParams->SellSum . ', ' .
				$apiMethodParams->SellCur . ', ' .
				$apiMethodParams->SellAccDt . ', ' .
				$apiMethodParams->BuyAccCt . ', ' .
				$apiMethodParams->BuySum . ', ' .
				$apiMethodParams->BuyCur . ', ' .
				$apiMethodParams->BuyAccDt . ', ' .
				$apiMethodParams->SellAccCt . ')');
	
			$retJSON->created = 'successful';
	
        }else{
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        
		return $retJSON;
    }
	
	// http://localhost/projects/api_test/?apitest.getPayments={"Deal":"1"}
	function getPayments($apiMethodParams) {
		
		$retJSON = $this->createDefaultJson();
			
        if (isset($apiMethodParams->Deal)){
            //Все ок параметры верные, их и вернем
            			
			$result = MySQLiWorker::get_data_from_pgsql('select * from vw_payments where deal_id = ' . $apiMethodParams->Deal);
	
			$retJSON->Payments = array();
	
			while ($row = pg_fetch_row($result)) {
				
				array_push(
					$retJSON->Payments, array(
						'sum' => $row[1],
						'cur' => $row[2],
						'acc_dt' => $row[3],
						'acc_ct' => $row[4],
						'num' => $row[5],
						'comment' => $row[6],
						'knp' => $row[7]
					)
				);
				
			}
			
        }else{
            $retJSON->errorno = APIConstants::$ERROR_PARAMS;
        }
        return $retJSON;
	}
}

?>