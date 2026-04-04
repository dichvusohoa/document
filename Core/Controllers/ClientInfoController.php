<?php
namespace Core\Controllers;
use Core\Models\Response;
use Core\Models\Session;
class ClientInfoController extends BaseController {
    protected function resolveParams(string $strFunctionName): array {
        switch ($strFunctionName) {
            case 'index':
                return [];//không có tham số
            default:    
                return [];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function index(): void {
        $initial_uri = $this->requestAuthContext->request()->json('initial_uri');
        $screen = $this->requestAuthContext->request()->json('screen');
        if($initial_uri !==null && $screen !==null ){
            Session::set('device_screen', $screen);
            $resp = ['status' => Response::SERVER_OK_STATUS, 'info' => ['initial_uri' => $this->request->post('initial_uri'), 'screen' => $this->request->post('screen')], 'extra' => null ];
        }
        else{
            $resp = ['status' => Response::SERVER_ERR_STATUS, 'info' => 'Missing information about initial_uri and screen', 'extra' => null ];
        }
        Response::sendJson($resp);
    }
}