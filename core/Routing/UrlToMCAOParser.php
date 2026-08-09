<?php
namespace Core\Routing;
class UrlToMCAOParser{
    protected MCABasic $mcaBasic;
    public function __construct(MCABasic $mcaBasic)
    {
        $this->mcaBasic = $mcaBasic;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function parse(string $strUrl): ?array{
        $strUri = parse_url($strUrl, PHP_URL_PATH) ?? '';
        if ($strUri === false  //lỗi cú pháp nghiêm trọng
        ) {
            //return null;
            throw new HttpException(404, "UrlToMCAOParser phân tích url: '{$strUrl}' thấy lỗi nghiêm trọng");
        }
        //trim($strUri, '/'): xóa dấu / ở đầu và cuối chuỗi
        $arrSegment = array_values(array_filter(explode('/', trim($strUri, '/')), 'strlen'));
       
        return $this->toMCAO($arrSegment);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAO(array $arrSegment): ?array
    {
        if (empty($arrSegment)) {
            return $this->buildDefaultMCAO();
        }

        $strFirstSegment = $arrSegment[0];

        if ($this->mcaBasic->moduleExists($strFirstSegment)) {
            return $this->toMCAOWithM(
                $strFirstSegment,
                $arrSegment
            );
        }

        if (
            $this->mcaBasic
                ->standaloneControllerExists($strFirstSegment)
        ) {
            return $this->toStCAOWithC(
                $strFirstSegment,
                $arrSegment
            );
        }

        return null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildDefaultMCAO(): ?array
    {
        $strModule = $this->mcaBasic->getDefaultModule();

        if ($strModule !== null) {
            return $this->buildDefaultMCAOWithM($strModule);
        }

        return $this->buildDefaultStCAO();
        
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //là step trung gian để buildDefaultMCAO khi đã có module
    protected function buildDefaultMCAOWithM(
        string $strModule,
        array $arrOtherParam = []
    ): ?array {
        $strController = $this->mcaBasic
            ->getDefaultControllerInModule($strModule);

        if ($strController === null) {
            return null;
        }

        return $this->buildDefaultMCAOWithMC(
            $strModule,
            $strController,
            $arrOtherParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //là buildDefaultMCAO trong trường hợp đặc biệt khi không có module
    protected function buildDefaultStCAO(): ?array
    {
        $strController = $this->mcaBasic
            ->getDefaultStandaloneController();

        if ($strController === null) {
            return null;
        }

        return $this->buildDefaultMCAOWithMC(
            null,
            $strController
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAOWithM(
        string $strModule,
        array $arrSegment
    ): ?array {
        /*
         * [1] khuyết:
         * dùng default controller và default action.
         */
        if (!isset($arrSegment[1])) {
            return $this->buildDefaultMCAOWithM($strModule);
        }

        $strControllerCandidate = $arrSegment[1];

        /*
         * [1] không phải controller:
         * dùng default controller + default action;
         * từ [1] trở đi là other params.
         */
        if (
            !$this->mcaBasic->controllerExistsInModule(
                $strModule,
                $strControllerCandidate
            )
        ) {
            return $this->buildDefaultMCAOWithM(
                $strModule,
                array_slice($arrSegment, 1)
            );
        }

        return $this->toMCAOWithMC(
            $strModule,
            $strControllerCandidate,
            $arrSegment,
            2
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toStCAOWithC(
        string $strController,
        array $arrSegment
    ): ?array {
        return $this->toMCAOWithMC(
            null,
            $strController,
            $arrSegment,
            1
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAOWithMC(
        ?string $strModule,
        string $strController,
        array $arrSegment,
        int $intActionPos
    ): ?array {
        /*
         * Action segment khuyết:
         * dùng default action.
         */
        if (!isset($arrSegment[$intActionPos])) {
            return $this->buildDefaultMCAOWithMC(
                $strModule,
                $strController
            );
        }

        $strActionCandidate = $arrSegment[$intActionPos];

        /*
         * Segment hiện tại không phải action:
         * dùng default action;
         * từ segment hiện tại trở đi là other params.
         */
        if (
            !$this->mcaBasic->actionExists(
                $strModule,
                $strController,
                $strActionCandidate
            )
        ) {
            return $this->buildDefaultMCAOWithMC(
                $strModule,
                $strController,
                array_slice($arrSegment, $intActionPos)
            );
        }

        /*
         * Segment hiện tại là action:
         * các segment phía sau là other params.
         */
        return $this->buildMCAO(
            $strModule,
            $strController,
            $strActionCandidate,
            array_slice($arrSegment, $intActionPos + 1)
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildDefaultMCAOWithMC(
        ?string $strModule,
        string $strController,
        array $arrOtherParam = []
    ): ?array {
        $strAction = $this->mcaBasic->getDefaultAction(
            $strModule,
            $strController
        );

        return $this->buildMCAO(
            $strModule,
            $strController,
            $strAction,
            $arrOtherParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCAO(
        ?string $strModule,
        string $strController,
        ?string $strAction,
        array $arrOtherParam = []
    ): ?array {
        if ($strAction === null) {
            return null;
        }

        return [
            MCAOInfo::FIELD_MODULE       => $strModule,
            MCAOInfo::FIELD_CONTROLLER   => $strController,
            MCAOInfo::FIELD_ACTION       => $strAction,
            MCAOInfo::FIELD_OTHER_PARAMS => array_values($arrOtherParam)
        ];
    }
}