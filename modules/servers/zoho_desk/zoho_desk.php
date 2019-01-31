<?php
use WHMCS\Database\Capsule;
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
function zoho_desk_MetaData()
{    
     try {
         if(!Capsule::schema()->hasTable('zoho_desk')){
    	       Capsule::schema()->create(
    	                                'zoho_desk',
    	                           function ($table) {
    	                                 $table->string('authtoken');
    	                                 $table->string('domain');
    	                                 $table->string('server');
    	                                 $table->string('zoid');
    	                                 $table->string('profileid');
    	                                 $table->string('superAdmin');
    	                               }
    	                        );
        }
        else {
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
        }
	} catch (Exception $e) {
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
    return array(
    	'DisplayName' => 'Zoho Desk',
    	'APIVersion' => '1.1',
    	'RequiresServer' => true,
    	'DefaultNonSSLPort' => '1111',
    	'DefaultSSLPort' => '1112',
    	'ServiceSingleSignOnLabel' => 'Login to Panel as User',
    	'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
    
}
function zoho_desk_ConfigOptions()
{
	        
    return array(
         // the radio field type displays a series of radio button options
        'Domain' => array(
            'Type' => 'radio',
            'Options' => 'com,eu,in,cn',
            'Description' => 'Choose your domain!',
        ),
        // a text field type allows for single line text input
        'Authtoken' => array(
            'Type' => 'text',
            'Size' => '50',
            'Description' => '<br><a href="https://accounts.zoho.com/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for US Domain. 
            <br><a href="https://accounts.zoho.eu/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for EU Domain. 
            <br><a href="https://accounts.zoho.com.cn/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for CN Domain. 
            <br><a href="https://accounts.zoho.in/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for IN Domain.',
        ),
       
    );
}
function zoho_desk_CreateAccount(array $params)
{
	$addonid;
	$urlOrg;
	$domain = $params['configoption1'];
	$test = $params['configoption2'];
	try {
	$curl = curl_init();
	$arrClient = $params['clientsdetails'];
	$planid = $params['configoptions']['Plan Type'];
	if($planid == 10214) {
	    $addonid = 10254;
	}
	else{
	    $addonid = 10255;
	}
	$noofusers = $params['configoptions']['No of users'];
	 
	$bodyArr = json_encode(array(
		//'JSONString' => array(
		"serviceid" => 4501,
		"email" => $arrClient['email'],
		"customer" => array(
		"companyname" => $arrClient['companyname'],
		"street" => $arrClient['address1'],
		"city" => $arrClient['city'],
		"state" => $arrClient['state'],
		"country" => $arrClient['countryname'],
		"zipcode" => $arrClient['postcode'],
		"phone" => $arrClient['phonenumber']
		),
		"subscription" => array(
		"plan" => $planid,
		"addons" => array(
		array(
		"id" => $addonid,
		"count" => $noofusers
		)
		),
		"payperiod" => "YEAR",
		"currency" => "1",
		"addprofile" => "true"
		),
		//),
	));
	$authtoken = array(
	"authtoken" => $test
	);
	 
	$bodyJson = array('JSONString' => $bodyArr, 'authtoken' => $test);
	$bodyJsn = json_encode($bodyJson);
        $curlOrg = curl_init();
       if($domain == 'cn')
	{
		$urlOrg = 'https://payments.zoho.com.'.$params['configoption1'].'/restapi/partner/v1/json/subscription';		
	}
	else 
	{
   		$urlOrg = 'https://payments.zoho.'.$params['configoption1'].'/restapi/partner/v1/json/subscription';
	}
        curl_setopt_array($curlOrg, array(
	      CURLOPT_URL => $urlOrg,
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => $bodyJson
	   ));

		$responseOrg = curl_exec($curlOrg);
		$respOrgJson = json_decode($responseOrg); 
		$getInfo = curl_getinfo($curlOrg,CURLINFO_HTTP_CODE);
		curl_close($curlOrg);
		$result = $respOrgJson->result;
		if(($result == 'success') && ($getInfo == '200')) {
		    $customid = $respOrgJson->customid;
		    if($customid != '') {
		        $pdo = Capsule::connection()->getPdo();
		        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0 );
		        //$pdo->beginTransaction();
		        try {
			        $statement = $pdo->prepare('insert into zoho_desk (authtoken,domain,server,zoid,profileid,superAdmin) values (:authtoken, :domain, :server, :zoid, :profileid, :superAdmin)');
	 
		            $statement->execute(
        		     [
        			   ':authtoken' => $test,
        			   ':domain' => $params['domain'],
        			   ':server' => $params['configoption1'],
        			   ':zoid' => $respOrgJson->customid,
        			   ':profileid' => $respOrgJson->profileid,
        			   ':superAdmin' => "true"              
        		    ]
        		 );
	 
        		 $pdo->commit();
        		 $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1 );
        		 } catch (\Exception $e) {
        			  return "Uh oh! {$e->getMessage()}".$urlChildPanel;
        			  $pdo->rollBack();
        		  }
	 
    		  return array ('success' => 'Desk Org has been created successfully.');
    		    }
    		    else if(($result == 'success') && (isset($respOrgJson->ERRORMSG))) {
    		        return 'Failed  ->  '.$respOrgJson->ERRORMSG;
    		    }
    		    else if ($getInfo == '400') {
		            $updatedUserCount = Capsule::table('tblproducts')
		            ->where('servertype','zoho_desk')
		            ->update(
        			  [
        			   'configoption2' => '',
        			  ]
		            );
			    }
			    else
        		{
        		    return 'Failed -->Description: '.$respOrgJson->status->description.' --->More Information:'.$respOrgJson->data->moreInfo.'--------------'.$getInfo;
        	    }
    		        
    		    
    		}
    		else{
    		    $errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  '.$errorMsg;
    		}
	 
	} catch (Exception $e) {
		logModuleCall(
		    'zoho_desk',
		    __FUNCTION__,
		    $params,
		    $e->getMessage(),
		    $e->getTraceAsString()
		);
		return $e->getMessage();
	    }
 
}
function zoho_desk_TestConnection(array $params)
{
    try {
	// Call the service's connection test function.
	$success = true;
	$errorMsg = '';
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	$success = false;
	$errorMsg = $e->getMessage();
    }
    return array(
	'success' => $success,
	'error' => $errorMsg,
    );
}
function zoho_desk_AdminServicesTabFields(array $params)
{
 
   try{
	   $paymenturl;
	   $url;
	   $cli = Capsule::table('zoho_desk')->where('domain',$params['domain'])->first();
	   $domain = $params['configoption1'];
	   if($domain == 'cn') 
	   {
	    $url = 'https://accounts.zoho.com.'.$domain.'/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi';
	    $paymenturl = 'https://payments.zoho.com.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
	  }
	  else
	  {
	    $url = 'https://accounts.zoho.'.$domain.'/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi';
	    $paymenturl = 'https://payments.zoho.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
	  }
	$authtoken = $params['configoption2'];
	if(!$authtoken == '') {
	$authtoken = '<h2 style="color:green;">Authenticated</h2>';
	}
	else {
	$authtoken = '<a href="'.$url.'" type="submit" target="_blank"> Click here </a> (Call only once for authenticating)';
	}
	$response = array();
	   
	return array(
	     'Authenticate' => $authtoken,
	     'Super Administrator' => $cli->superAdmin,
	     'ZOID' => $cli->zoid, 
		'URL to Manage Customers' => '<a href="'.$paymenturl.'" target=_window>Click here</a>'
	    );
	 
    } catch (Exception $e) {
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
	    return array();
}
function zoho_desk_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['zoho_desk_original_uniquefieldname'])
	? $_REQUEST['zoho_desk_original_uniquefieldname']
	: '';
    $newFieldValue = isset($_REQUEST['zoho_desk_uniquefieldname'])
	? $_REQUEST['zoho_desk_uniquefieldname']
	: '';
return array('success' => $originalFieldValue);
    if ($originalFieldValue != $newFieldValue) {
	try {
	} catch (Exception $e) {
	    logModuleCall(
	        'zoho_desk',
	        __FUNCTION__,
	        $params,
	        $e->getMessage(),
	        $e->getTraceAsString()
	    );
	}
    }
}
function zoho_desk_ServiceSingleSignOn(array $params)
{
    try {
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_desk_AdminSingleSignOn(array $params)
{
    try {
	// Call the service's single sign-on admin token retrieval function,
	// using the values provided by WHMCS in `$params`.
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_desk_ClientArea(array $params)
{
    $serviceAction = 'get_stats';
    $templateFile = 'templates/overview.tpl';
	$deskurl;
	$domain = $params['configoption1'];
    if($domain == 'cn')
    {
        $deskurl = 'https://desk.zoho.com.cn';
    }
    else
    {
        $deskurl = 'https://desk.zoho.'.$domain;
    }
    try {
      $cli = Capsule::table('zoho_desk')->where('zoid',$params['zoid'])->first();
      $urlToPanel = $cli->url;
	return array(
	    'tabOverviewReplacementTemplate' => $templateFile,
	    'templateVariables' => array(
	     'deskUrl' => $deskurl
	     //'panelUrl' => $urlToPanel
	    ),
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_desk',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	// In an error condition, display an error page.
	return array(
	    'tabOverviewReplacementTemplate' => 'error.tpl',
	    'templateVariables' => array(
	        'usefulErrorHelper' => $e->getMessage(),
	    ),
	);
    }
}
