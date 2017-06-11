<?php 
class InfoDB{
	/**
	 * 
	 * 获取流程配置
	 * @param $wf_uid
	 */
	function getWorkflowById($wf_uid) {
		$wf_sql = "select wf_filename, wf_name from t_wf_workflow where wf_uid=" . sqlFilter ( $wf_uid, 1 );
		$wf_result = DBCommon::query ( $wf_sql );
		$wf_row = DBCommon::fetch_array ( $wf_result );
		$dxml = $wf_row ['wf_filename'];
		return $dxml;
	}
	/**
	 * 
	 * 获取流程实例
	 * @param $wfuid
	 */
	function getWfEntry($et_uid) {
		$sql = "select wf_uid,et_createuser from t_wf_entry where et_uid=" . sqlFilter ( $et_uid, 1 );
		// echo $sql;
		$query = DBCommon::query ( $sql );
		$row = DBCommon::fetch_array ( $query );
		return $row;
	}
	/**
	 * 根据单据ID获取流程信息
	 *
	 * @param string $etuid	实例id
	 * @param string $ssn	工资号
	 * @return array() 流程信息
	 */
	function workflowInfo($etuid, $ssn) {
		$workflow = array ();
		if ($etuid == '' || $ssn == '') {
			return $workflow;
		}
		$sql = "select et_state,et_createuser from t_wf_entry where et_uid='$etuid'";
		$result = DBCommon::query ( wf_iconvutf ( $sql ) );
		if ($row = DBCommon::fetch_array ( $result )) {
			//流程状态
			$workflow ['et_state'] = $row ['et_state'];
			//单据起草人
			$workflow ['et_createuser'] = $row ['et_createuser'];
			//是否会签到起草人
			$isCreateUser = false;
			$cs_sql = "select cs_id,cs_updateby,cs_salarysn,cs_status,wf_uid wfuid,cs_parentid 
			from t_wf_currentstep where  et_uid='$etuid' and cs_salarysn='$ssn' and cs_status<> 'Underway' order by cs_endtime desc ";
			$cs_result = DBCommon::query ( $cs_sql );
			if ($cs_row = DBCommon::fetch_array ( $cs_result )) {
				//单据流程环节
				$workflow ['cs_id'] = $cs_row ['cs_id'];
				//单据当前处理人
				$workflow ['cs_updateby'] = $cs_row ['cs_updateby'];
				$workflow ['cs_salarysn'] = $cs_row ['cs_salarysn'];
				//单据当前流程ID
				$workflow ['wf_uid'] = $cs_row ['wfuid'];
				//流程当前状态
				$workflow ['cs_status'] = $cs_row ['cs_status'];
				//cs_parentid 当前环节是否是并行流程中
				$workflow ['cs_parentid'] = $cs_row ['cs_parentid'];
				//csid 会签为 -600 或 -650
				$csid = $cs_row ['cs_id'];
				if (($csid == '-600' || $csid == '-650' || $csid == '1') && strtoupper ( $ssn ) == strtoupper ( $workflow ['et_createuser'] ) && strtoupper ( $ssn ) == strtoupper ( $workflow ['cs_updateby'] )) {
					$isCreateUser = true;
					
				}
			} else {
				$workflow ['cs_id'] = '';
				//单据当前处理人
				$workflow ['cs_updateby'] = '';
				$workflow ['cs_salarysn'] = '';
				//单据当前流程ID
				$workflow ['wf_uid'] = '';
				//流程当前状态
				$workflow ['cs_status'] = '';
				//cs_parentid 当前环节是否是并行流程中
				$workflow ['cs_parentid'] = '';
			}
			$workflow ['isCreateUser'] = $isCreateUser;
		}
		return $workflow;
	}
	/**
	 * 新做单据不再需要此方法
	 * 根据ID获取当前流程信息
	 */
	function getCurrentStep($uid) {
		if (isNull ( $uid )) {
			return array ();
		}
		$wf_sql = 'select et_uid, cs_salarysn, cs_id, cs_status, cs_updateby, steplock,wf_uid 
		from t_wf_currentstep where uid=' . sqlFilter ( $uid, 1 ) . ' order by cs_endTime desc ';
		$result = DBCommon::query ( $wf_sql );
		
		if ($row = DBCommon::fetch_array ( $result )) {
			return $row;
		}
		return array ();
	}
	/**
	 * Enter 按配置取人员
	 *
	 * @param unknown_type $group_uid
	 * @return unknown
	 */
	public function getUserByConfig($group_uid) {
		$wf_users = array ();
		$wf_sql = "select usercode from t_wf_steps_user 
					where group_uid = '$group_uid'" ;
		$wf_result = DBCommon::query ( $wf_sql );
		while ( $row = DBCommon::fetch_array ( $wf_result ) ) {
			array_push ( $wf_users, $row ['usercode'] );
		}
		return $wf_users;
	}
	/**
	 * Enter 取条件判断表达式
	 * 示例： #单据变量# == @配置变量@ && #单据金额# > 10000
	 * @return unknown
	 */
	public function getConditionExpression($nextstepid) {
		$commandContext = CommandContext::getInstance();
		$wfuid = $commandContext->getWfuid();
		$stepid = $commandContext->getStepid();
		//条件判断表达式
		$wf_sql = "select id,expression 
					from t_wf_steps_condition 
					where type = 'condition' and wf_uid = '$wfuid' and step_id = '$stepid' and next_step_id='$nextstepid'  
					ORDER BY sort " ;
		$wf_result = DBCommon::query ( $wf_sql );
		//查询变量信息
		$varList = array();
		$sql = "SELECT expression_key,expression_value FROM t_wf_steps_condition_var 
				WHERE wf_uid = '$wfuid' 
				order by expression_key ";
		$result_var = DBCommon::query ( $sql );
		while ( $row_var = DBCommon::fetch_array ( $result_var ) ) {
			$row_var['expression_key'] = wf_iconvutf($row_var['expression_key']);
			$row_var['expression_value'] = wf_iconvutf($row_var['expression_value']);
			array_push($varList, $row_var);	
		}
		
		$expressions = array();
		if ( $row = DBCommon::fetch_array ( $wf_result ) ) {
			$row['expression'] = wf_iconvutf($row['expression']);
			$docVars = explode("#", $row['expression']);
			foreach ($docVars as $docVar){
				if(!isNull($docVar) && !preg_match("/[<>=@'&\(\)\|]+|[\s]+/",$docVar)){
					$commandContext->getTempVar($docVar);//尝试下变量是否存在，如果不存在会自动加载
				}
			}
			if(strpos($row['expression'],"@") ){//如果使用了配置变量，取配置信息
				//替换表达式中的配置变量
				$row['expression'] = $this->convertExpression($row['expression'], $varList);
			}
			//替换单据变量
			$commandContext = CommandContext::getInstance();
			foreach ( $commandContext->getTempVar() as $key => $value ) {
				if (is_string($value) || is_numeric($value)){
					if(is_string($value)){
						$value = "'".$value."'";
					}
					$row['expression'] = str_replace ( $key, $value, $row['expression'] );
				}	
			}
			array_push($expressions, $row);
		}
		return $expressions;
	}
	/**
	 * Enter 取角色判断表达式
	 * 示例： #单据变量# == @配置变量@ && #单据金额# > 10000
	 * @return unknown
	 */
	public function getRoleExpression() {
		$commandContext = CommandContext::getInstance();
		$wfuid = $commandContext->getWfuid();
		$stepid = $commandContext->getNextStepid();
		$wf_sql = "select id,expression,group_uid 
					from t_wf_steps_condition 
					where type='role' and wf_uid = '$wfuid' and step_id = '$stepid'  
					ORDER BY sort " ;
		$wf_result = DBCommon::query ( $wf_sql );
		//查询变量信息
		$varList = array();
		$sql = "SELECT expression_key,expression_value FROM t_wf_steps_condition_var 
				WHERE wf_uid = '$wfuid' 
				order by expression_key ";
		$result_var = DBCommon::query ( $sql );
		while ( $row_var = DBCommon::fetch_array ( $result_var ) ) {
			$row_var['expression_key'] = wf_iconvutf($row_var['expression_key']);
			$row_var['expression_value'] = wf_iconvutf($row_var['expression_value']);
			array_push($varList, $row_var);	
		}
		
		$expressions = array();
		while ( $row = DBCommon::fetch_array ( $wf_result ) ) {
			$row['expression'] = wf_iconvutf($row['expression']);
			$docVars = explode("#", $row['expression']);
			foreach ($docVars as $docVar){
				if(!isNull($docVar) && !preg_match("/[<>=@'&\(\)\|]+|[\s]+/",$docVar)){
					$commandContext->getTempVar($docVar);//尝试下变量是否存在，如果不存在会自动加载
				}
			}
			if(strpos($row['expression'],"@") ){//如果使用了配置变量，取配置信息
				//替换表达式中的配置变量
				$row['expression'] = $this->convertExpression($row['expression'], $varList);
			}
			//替换单据变量
			$commandContext = CommandContext::getInstance();
			foreach ( $commandContext->getTempVar() as $key => $value ) {
				if (is_string($value) || is_numeric($value)){
					if(is_string($value)){
						$value = "'".$value."'";
					}
					$row['expression'] = str_replace ( $key, $value, $row['expression'] );
				}	
			}
			array_push($expressions, $row);
		}
		return $expressions;
	}
	/**
	 * 
	 * 表达式必须为  #单据变量# == @配置变量@ 的形式
	 * @param unknown_type $expression
	 * @param unknown_type $varList
	 */
	private function convertExpression($expression,$varList,$overLoop=50){
		if($overLoop == 0){//防止死循环，定义最大循环次数
			return 0;
		}
		$index = strpos($expression,"@");//配置变量开始位置
		if($index===false){//如果表达式中没有配置变量了返回
			return $expression;
		}else{
			$end = strpos($expression,"@",$index+1);//配置变量结束位置
			if($end===false){
				return $expression;
			}
			$var = substr($expression,$index,$end-$index+1);//配置变量字符串
			$beforeExp = substr($expression,0,$end+1);//配置变量之前的字符串
			$endExp = strrpos($beforeExp,"#");//单据变量结束的位置
			$beforeExp = substr($expression,0,$endExp); //
			$indexExp = strrpos($beforeExp,"#"); //单据变量开始的位置
			$docVar = substr($expression,$indexExp,$endExp-$indexExp+1); //单据变量内容
			$oneExp = substr($expression,$indexExp,$end-$indexExp+1);//一个完整的表达式
			//echo "index:".$index."end:".$end."var:".$var."beforeExp:".$beforeExp."endExp:".$endExp."indexExp:".$indexExp;
			//echo "<br>oneExp:".$oneExp;
			$newExp = "";//替换后的表达式
			foreach ($varList as $row){
				if($var == $row['expression_key']){
					$expression_value = $row['expression_value'];
					if(!is_numeric($expression_value)){
						$expression_value = "'".$expression_value."'";
					}
					if(strpos($oneExp,"!=")){
						$newExp .= str_replace ( $row['expression_key'], $expression_value, $oneExp )." && ";
					}else if(strpos($oneExp,"==")){
						$newExp .= str_replace ( $row['expression_key'], $expression_value, $oneExp )." || ";;
					}else{
						$newExp = "";
					}
				}
			}
			if($newExp != ""){
				$newExp = "(".substr($newExp, 0,strlen($newExp)-4).")";
				//替换单据变量
				$commandContext = CommandContext::getInstance();
				foreach ( $commandContext->getTempVar() as $key => $value ) {
					if (is_string($value) || is_numeric($value)){
						if(is_string($value)){
							$value = "'".$value."'";
						}
						$newExp = str_replace ( $key, $value, $newExp );
					}
				}
			}
			//echo "<br>newExp:".$newExp;
			$expression = substr_replace($expression,"",$indexExp,$end-$indexExp+1);//将原表达式清空
			//echo "<br>expression1:".$expression;
			$expression = substr_replace($expression,$newExp,$indexExp,0);//将新表达式插入
			//echo "<br>expression2:".$expression;
			return $this->convertExpression($expression,$varList,--$overLoop);

		}
	
	}
}