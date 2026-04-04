class editHierarchy{
    /*Về mặt tổ chức dữ liệu editHierarchy có những điểm giống và khác với searchCombo,autoTable, ulMenu
     * searchCombo thì dữ liệu của nó là tĩnh nên nó lưu ngay một jsonData trong contructor
     * autoTable thì mỗi lần load(phân trang, đổi số item trong một page) thì là một data mới nên nó không lưu jsonData mà dùng createHTMLTable để vẽ giao diện với tham số đầu vào là data 
     * ulMenu thì load dữ liệu có tính thừa kế (load từng phần), nó dùng chinh cấu trúc giao diện UL/LI để lưu và cập nhật laị data nó load về
     * editHierarchy không có cấu trúc UL/LI như ulMenu nên nó cần một cấu trúc nội tại jsonData để lưu dữ liệu
     * @param {type} htmlContainer
     * @param {type} jsonData
     * @param {type} options
     * @returns {editHierarchy}
     */
    /*-------------------------------------------------------------------------------------------------*/
    isErrorCombo(){
        if(this.inputDest.hasAttribute("err")){
           return true; 
        }
        else{
            return false;
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    async loadData(anchorElement,arrChain){
        let iHeight = Math.round(2.0*this.htmlContainer.clientHeight) + "px"; //input loading có chiều cao bằng 2 lần thẻ containter bao ngoài
        common.loadingStatus(anchorElement,true,"Đang tải dữ liệu",iHeight);
        let edtHrchObj = this;
        try{
            let response = await fetch(anchorElement.href);
            if(response.status !==200){
                throw Error (response.status + ". " + response.statusText);
            }
            let jsonRespData = await response.json();
            let sNode = common.getURLParam(edtHrchObj.sBaseUrl,PART_DATA); 
            
            //quét lỗi toàn data (jsonRespData) để chặn các lỗi hệ thống
            common.showUIAndControlError(anchorElement,jsonRespData,
                function(){},
                null, 
                true // throw Error để không chạy các lệnh dưới
            );
            common.showUIAndControlError(anchorElement,jsonRespData[sNode],function(){
                let arrData =  jsonRespData[sNode]["info"]["data"];//jsonRespData[sNode]["info"]["data"] thường có dạng array
                let arrProp = [];
                arrProp.push.apply(arrProp,arrChain);
                for(let idx in arrData){
                    let arrTmp = arrProp.slice();//copy array để khỏi ảnh hưởng đến arrProp
                    let sNodeId = arrData[idx][edtHrchObj.options.paramDataNames.nodeId];
                    arrTmp.push(sNodeId);
                    arrTmp.push("_leaf_info");
                    edtHrchObj.extAStoreData.setObjectValue(arrTmp,arrData[idx],"replace");
                    arrTmp.pop();//remove "_leaf_info" elêmnt
                    arrTmp.push("_loaded");
                    edtHrchObj.extAStoreData.setObjectValue(arrTmp,false,"keep_old_value");
                }
                arrProp.push("_loaded");
                edtHrchObj.extAStoreData.setObjectValue(arrProp,true,"replace");
                
            });
        }
        catch(error){
            console.log(error);
        }
        finally{
            common.loadingStatus(anchorElement,false);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    getDataForCombo(arrChain){
        let jsonData;
        if(arrChain.length ===0){
            jsonData = this.extAStoreData.data;
        }
        else{
            jsonData = this.extAStoreData.getObjectValue(arrChain);
        }
        let objResult = {};
        for(let key in jsonData){
            if(key !== "_leaf_info" && key !== "_loaded"){
                objResult[key] = jsonData[key]["_leaf_info"][this.options.paramDataNames.title];
            }
        }
        return objResult;
    }
    /*-------------------------------------------------------------------------------------------------*/
    deleteCurrentCombo(){
        if(!this.searchCombo){
            return null;
        }
        this.searchCombo.clearCombo();
        this.searchCombo = null;
        this.inputDest.style.display = "none";
        this.inputDest.removeAttribute("err");
        // khi đã xóa combo cũ thì cần cho thẻ A cũ tương ứng với combo đó hiện ra
        let aHiddenHtmlA = this.inputDest.nextSibling; 
        aHiddenHtmlA.style.display = "flex"; 
        return aHiddenHtmlA;
    }
    /*-------------------------------------------------------------------------------------------------*/
    createCombo(anchorElement,jsonDataCmb){
         /*phải lưu trước ở đây vì sau này khi inputDest hiện ra(inputDest.style.width = 'flex') thì  có thể
        do tính chất flex của các phần tử A, INPUT có độ rộng mềm dẻo nên độ rộng của anchorElement có thể thay đổi, ví
        dụ như co lại
        */
        let edtHrchObj = this;
        let iATagWidth = anchorElement.offsetWidth; 
        this.htmlContainer.insertBefore(this.inputDest,anchorElement);//đăt inputDest trước anchorElement
        this.inputDest.style.display = "flex";
        //this.inputDest.style.width = `calc(${iATagWidth}px + 1rem)`;// thêm 1 rem vì sau này tạo combo còn có múi tên sổ xuống bên phải
        anchorElement.style.display = "none";
        if(anchorElement.dataset.nodeId === "null" || anchorElement.dataset.nodeId === "?"){
            this.inputDest.value = "";
        }
        else{
            this.inputDest.value = anchorElement.innerText;
        }
        //let atbts = {"onInputCommit":edtHrchObj.selectItem,"sFunctComboOut":edtHrchObj.comboOut,"constraints":{"required":true,"must_be_in_list":true}};
        let options = {"onInputCommit":edtHrchObj.onInputCommit,"onInputCommitError":edtHrchObj.onInputCommitError,
            "constraints":{"required":true,"must_be_in_list":true}};
        let smallCboObj = new searchCombo(this.inputDest,jsonDataCmb,null,options);
        this.searchCombo = smallCboObj;
        this.searchCombo.divCombo.style.width = `calc(${iATagWidth}px + 3rem)`;// thêm 3 rem vì sau này tạo combo còn có múi tên sổ xuống bên phải
        /*Begin Thêm các dữ liệu jsonData và options cho divCombo để dùng cho hàm static  getEditHierarchyValue sau này*/
        this.searchCombo.divCombo.jsonData  = jsonDataCmb;
        this.searchCombo.divCombo.options   = options;
        /*End Thêm các dữ liệu jsonData và options cho divCombo để dùng cho hàm static  getEditHierarchyValue sau này*/
        let inputArrow  = this.inputDest.nextSibling; // phím mũi tên của combo
        inputArrow.click();//combo xổ xuống*/
        
    }
    /*-------------------------------------------------------------------------------------------------*/
    getParentChain(anchorElement){
        let htmlEle =  this.htmlContainer.firstChild;
        let arrResult = [];
        let idx = 0;
        while(htmlEle && htmlEle !==anchorElement){
            if(htmlEle && htmlEle.tagName && htmlEle.tagName === "A"){
                arrResult[idx] = htmlEle.dataset.nodeId;
                idx++;
            }
            htmlEle =    htmlEle.nextSibling;
        }
        return arrResult;
    }
    /*-------------------------------------------------------------------------------------------------*/
    deleteAElementsAfter(htmlControl){
        //Begin xóa các A Element đứng sau htmlControl
        let htmlElm = this.htmlContainer.lastChild;
        while(htmlElm && htmlElm !== htmlControl){
            let prevElement = htmlElm.previousSibling;
            if(htmlElm.tagName === "A"){
                this.htmlContainer.removeChild(htmlElm);
            }
            htmlElm = prevElement;
        }
        //End
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Giá trị isChanged là do trong combo tự xử lý và điền vào khi phát hiện ra có sự thay đổi key.
     * Xem trong code jsearch_combo*/
    onInputCommit = (inputDest,key,isChanged,eventname)=>{
        if(isChanged === true){
            this.isValidated = false;
        }
        this.updateNodeId(inputDest,isChanged);
    }
    /*-------------------------------------------------------------------------------------------------*/
    onInputCommitError = (inputDest,key,isChanged,eventname)=>{
        if(isChanged === true){
            this.isValidated = false;
        }
        let sErCss = ERR_DATA.getCssAttrErrFromObjErr(key);
        inputDest.setAttribute("err",sErCss);
        inputDest.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Sử dụng khi cập nhật lại giá trị tại một nodeId anchorElement. Về mặt giao diện là khi chọn giá trị trên
     * combo
    * @param {type} inputDest
     * @returns {undefined}
     */
    updateNodeId(inputDest,isChanged){
        let sNodeId = this.searchCombo.getKey();
        this.inputDest.removeAttribute("err");
        if(isChanged === false){
            let anchorElement = this.deleteCurrentCombo();
            anchorElement.focus();
            return;
        }
        //Begin: cập nhật lại giá trị innerHTML,nodeId, isLeaf cho anchorElement
        let anchorElement = inputDest.parentNode.nextSibling; //anchorElement đamg có display = none và nằm ngay sau combo
        anchorElement.innerHTML = inputDest.value;
        let arrChain = this.extAStoreData.findKey(sNodeId);
        //let arrChain = this.getParentChain(anchorElement);
        let jsonData  = this.extAStoreData.getObjectValue(arrChain);
        if(jsonData){
            anchorElement.dataset.nodeId = jsonData["_leaf_info"][this.options.paramDataNames.nodeId];
            anchorElement.dataset.isLeaf = common.isNumeric(jsonData["_leaf_info"][this.options.paramDataNames.isLeaf]) ;
            /*lỗi format jsonData["_leaf_info"][this.options.paramDataNames.isLeaf] thì common.isNumeric return false
            phải chuyển sang dạng string "true", false vì đó là đặc điểm của kiểu dữ liệu dataset */
            anchorElement.dataset.isLeaf = anchorElement.dataset.isLeaf === false || anchorElement.dataset.isLeaf <=0 ? "false" : "true";
        }
        //End: cập nhật lại giá trị innerHTML,nodeId, isLeaf cho anchorElement
        this.deleteCurrentCombo();
        this.deleteAElementsAfter(anchorElement);
        anchorElement.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    createTempChildATag(htmlPrevA){
        let htmTempA = document.createElement("A");
        htmTempA.innerHTML = "&#8226;&#8226;&#8226;"; //dấu ...
        let sUrl = common.setURLParam(htmlPrevA.href,this.options.paramUrlNames.nodeId,htmlPrevA.dataset.nodeId);
        htmTempA.href = sUrl;
        htmTempA.dataset.nodeId = "?";//chưa xác định
        htmTempA.dataset.isLeaf = "true"; /*nút tạm thời nên đương nhiên phải là nút lá*/
        this.htmlContainer.appendChild(htmTempA);
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Dịch chuyển nút delete ra phía trước anchorElement */
    moveDeleteButton(anchorElement){
        this.htmlContainer.insertBefore(this.btnDelete,anchorElement);
        this.btnDelete.style.display = "flex";
        this.btnDelete.style.width = `calc(${this.btnDelete.offsetHeight}px)`;//NÚT HÌNH VUÔNG
    }
    /*-------------------------------------------------------------------------------------------------*/
    static createHtmRootA(sUrl){
        let htmRootA = document.createElement("A");
        htmRootA.innerHTML = "&#9679;";//hình tròn màu đen lớn
        htmRootA.href = sUrl;
        htmRootA.dataset.nodeId = "null";
        htmRootA.dataset.isLeaf = "false";
        return htmRootA;
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Tạo một dãy các anchorElement liền kề nhau dựa vào thông tin chứa trong arrHierarchy*/
    static createHtmlASequence(arrHierarchy,sBaseUrl,options){
        const fragment = new DocumentFragment();
        if(arrHierarchy === null ||arrHierarchy.length ===0){ //ROOT level
            let sUrl = common.setURLParam(sBaseUrl,options.paramUrlNames.pgz,0);//lấy toàn bộ không phân trang
            let aTagRoot = editHierarchy.createHtmRootA(sUrl); 
            fragment.appendChild(aTagRoot);
            return fragment;
        }
        for(let i=0;i<arrHierarchy.length;i++){
            let aTag = document.createElement("A");
            aTag.innerHTML = arrHierarchy[i][options.paramDataNames.title];
            let sUrl = sBaseUrl;
            if(i>0){
                //từ phần tử thứ 2 trở đi thì sẽ có parent node_id
                sUrl = common.setURLParam(sBaseUrl,options.paramUrlNames.nodeId,arrHierarchy[i-1][options.paramDataNames.nodeId]);
            }
            sUrl = common.setURLParam(sUrl,options.paramUrlNames.pgz,0);//lấy toàn bộ không phân trang
           // aTag.href = "#";
            aTag.href = sUrl;
            aTag.dataset.nodeId = arrHierarchy[i][options.paramDataNames.nodeId];
            aTag.dataset.isLeaf = arrHierarchy[i][options.paramDataNames.isLeaf];
            fragment.appendChild(aTag);
        }
        return fragment;
    }
    /*-------------------------------------------------------------------------------------------------*/
    async anchorClick (anchorElement ){
        //không dùng this.extAStoreData.findKey được mà phải dùng this.getParentChain vì this.extAStoreData
        //có thể không hoàn chỉnh từ gốc đến anchorElement 
        let arrChain = this.getParentChain(anchorElement );
        let arrTmp = arrChain.slice();
        arrTmp.push("_loaded");
        let isLoaded = this.extAStoreData.getObjectValue(arrTmp);
       // let jsonData = this.extAStoreData.getObjectValue(arrChain);
        //let keys = jsonData === null ? null : Object.keys(jsonData);
        if(isLoaded === null || isLoaded === false){ // chưa tồn tại dữ liệu sub-items
            //console.log("chưa có dữ liệu " + anchorElement .innerHTML);
            await this.loadData(anchorElement ,arrChain);
        }
        else{
            //console.log("đã có dữ liệu " + anchorElement .innerHTML);
        }
        let jsonDataCmb = this.getDataForCombo(arrChain);
        this.deleteCurrentCombo(); //nếu đã có một combo control ở đâu đó thì xóa nó
        this.createCombo(anchorElement ,jsonDataCmb);// tạo combo ở vị trí anchorElement  hiện tại
    }
    /*-------------------------------------------------------------------------------------------------*/
    cutOffChain = () =>{
        this.isValidated = false;
        this.deleteAElementsAfter(this.btnDelete);
        let htmlFirst  = this.htmlContainer.firstChild;
       // console.log(htmlFirst);
        //Begin Tìm phần tử A đứng liền trước btnDelete
        let htmlEle = this.btnDelete;
        while(htmlEle && htmlEle !==htmlFirst && htmlEle.tagName !== "A"){
            htmlEle =    htmlEle.previousSibling;
        }
        //End Tìm phần tử A đứng liền trước btnDelete
        if(htmlEle && htmlEle.tagName === "A"){
            htmlEle.focus();
        }
        else{
            //tức là this.btnDelete đứng đầu tiên
            let sUrl = common.setURLParam(this.sBaseUrl,this.options.paramUrlNames.pgz,0);//lấy toàn bộ không phân trang
            sUrl = common.setURLParam(sUrl,this.options.paramUrlNames.nodeId,null);//root node nên xóa tham số node_id đi
            let aTagRoot = editHierarchy.createHtmRootA(sUrl); 
            this.htmlContainer.insertBefore(aTagRoot, this.htmlContainer.firstChild);
            aTagRoot.focus();
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    anchorFocusIn(anchorElement){
       // console.log("hàm anchorFocusIn " + anchorElement.style.outline);
        let edtHrchObj = this;
        let aElements = edtHrchObj.htmlContainer.querySelectorAll('A');
        let aLast =  aElements[aElements.length-1];
        let isRootA = (aElements.length === 1 && anchorElement.dataset.nodeId === "null");// có một nút A duy nhất và nodeId ===null
        if( anchorElement.dataset.isLeaf ==="false" && //không phải nút lá (có nút con cháu)
            anchorElement === aLast &&                   // thẻ A cuối cùng trong dãy 
            anchorElement.dataset.nodeId !=="null"       //không phải nút gốc, nút gốc cũng là một trạng thái chưa xác định tạm thời nên không cho tạo nút temp kế tiếp 
            ){  
            edtHrchObj.createTempChildATag(anchorElement);    
        }
        if(isRootA){
            this.btnDelete.style.display = "none";
        }
        else{
            this.moveDeleteButton(anchorElement);
        }
        //anchorElement.focus();
       
    }
    /*-------------------------------------------------------------------------------------------------*/
    moveLeftRight =(control,enableFocusControls,isMovingToLeft)=>{
        const lrControl = common.getNextOrPrevControl(control,enableFocusControls,isMovingToLeft);
        if(lrControl){
            lrControl.focus();
            return true;
        }
        return false;
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho htmlContainer*/
    focusIn=(event)=>{
        let control = event.target;
        if(control.tagName === "A") {
            this.anchorFocusIn(control);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho htmlContainer*/
    click =(event)=>{
        let control = event.target;
        if(control.tagName === "A"){
            event.preventDefault();
        }
        if(this.isErrorCombo()){
            this.inputDest.focus();
            return;
        }
        if(control.tagName === "A"){
            this.anchorClick (control);
        }
        else if(control === this.btnDelete){
            this.cutOffChain();
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownArrowLeftAndRight(event){
        const control = event.target;
        const keyCode = event.key;

        // Kiểm tra xem có phải là input thuộc loại combo không
        const isComboInput = control.tagName === "INPUT" && control.parentNode !== this.htmlContainer;
        // Kiểm tra điều kiện di chuyển con trỏ
        const isEnableMoving = this.canMoveCursor(isComboInput, control, keyCode);
        if (!isEnableMoving) {
            return;
        }
        // Lấy các phần tử cần di chuyển focus
        const enableFocusControls = this.getFocusableControls();
        // Di chuyển focus trái phải
        const isMoved = this.moveLeftRight(control, enableFocusControls,keyCode === "ArrowLeft");
        if (isMoved) {
            event.stopPropagation(); // Ngừng sự kiện để không lan tới các phần tử cha
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Hàm này chỉ xử lý khi control là A*/
    keyDownEnter(event){
        let control = event.target;
        event.preventDefault(); // chặn cư xử mực định của A link khi Enter
        if(this.isErrorCombo()){
            this.inputDest.focus();
        }
        else{
            this.anchorClick(control);
        }
        event.stopPropagation();//không cho các parent cao  hơn xử lý sự kiện
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho htmlContainer*/
    keyDown=(event)=>{
        let control = event.target;
        let keyCode = event.key;
        if(keyCode === "Enter" && control.tagName === "A"){
            this.keyDownEnter(event);
        }
        else if(keyCode === "ArrowLeft" || keyCode === "ArrowRight"){
            this.keyDownArrowLeftAndRight(event);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    canMoveCursor = (isComboInput, control, keyCode) => {
        if (!isComboInput) return true; // Nếu không phải combo input thì luôn cho phép di chuyển
        const isAtStart = control.selectionStart === 0 && keyCode === "ArrowLeft";
        const isAtEnd = control.selectionStart === control.value.length && keyCode === "ArrowRight";
        return isAtStart || isAtEnd;
    };
    /*-------------------------------------------------------------------------------------------------*/
    // Hàm lấy các phần tử có thể nhận focus
    getFocusableControls = () => {
        return this.htmlContainer.querySelectorAll(
            ":scope > input:not([style*='display: none'])," +
            ":scope > a:not([style*='display: none'])," +
            ":scope > div > input:not([type='button'])"
        );
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho inputDest*/
    input=()=>{
        this.isValidated = false;
        if(this.searchCombo.divListItem.style.display === "none"){
            return;
        }
        if(this.searchCombo.divCombo.clientWidth < this.searchCombo.divListItem.clientWidth){
            this.searchCombo.divCombo.style.width = `calc(${this.searchCombo.divListItem.clientWidth}px + 3.0rem)`;//thêm 3.0 rem vì còn input arrow bên phải
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    initEvent(htmlContainer){
        htmlContainer.addEventListener("focusin",this.focusIn);
        htmlContainer.addEventListener("click",this.click);
        htmlContainer.addEventListener("keydown",this.keyDown);
        
        this.inputDest.addEventListener("input",this.input);
        //this.inputDest.addEventListener("change",this.validate);
    }
    /*-------------------------------------------------------------------------------------------------*/
    clearEditHierarchy(){
        let htmlElm = this.htmlContainer.lastChild;
        while(htmlElm){
            let htmlPrevElm = htmlElm.previousSibling;
            if(htmlElm.tagName !=="A"){
                this.htmlContainer.removeChild(htmlElm);
            }
            htmlElm = htmlPrevElm;
        } 
        this.htmlContainer.removeEventListener("click",this.click);
        this.htmlContainer.removeEventListener("keydown",this.keyDown);
        this.htmlContainer.removeEventListener("focusin",this.focusIn);
    }
    /*-------------------------------------------------------------------------------------------------*/
    static isNodeIdElementOfParent(htmlElement){
        if (htmlElement === null || htmlElement.style.display === "none"){
            return false;
        }
        if(htmlElement.tagName ===  "DIV"){
            return true;
        }
        else if(htmlElement.tagName ===  "A"){
            if(htmlElement.dataset.nodeId === "?"){
                return false;
            }
            else{
                return true;
            }
        }
        return false;
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*return:
     * - nếu có divCombo ở bất kỳ đâu và divCombo giá trị lỗi thì trả về giá trị lỗi đó
     * - null nếu chỉ có một thẻ ROOT A
     * - Tìm các phần tử con của htmlContainer từ dưới lên thỏa mãn tagName === DIV 
     * hoặc (tagName === "A") và data-node-id không phải là "?"
     * Nếu phần tử tìm được là DIV thì trả về key của combo
     * Nếu phần tử tìm được là A thì trả về data-node-id
     * Nếu không tìm được thì return {"unknown_error":ERR_DATA["unknown_error"]};
     */
    static getEditHierarchyValue(htmlContainer){
        let divCombo = htmlContainer.querySelector(":scope>div");
        let key = null;
        if(divCombo){
            let inputDest = divCombo.querySelector(":scope>input:not([style*='display: none']):not([type='button'])")
            key = searchCombo.getCmbKey(inputDest.value.trim(),divCombo.jsonData,null,divCombo.options["constraints"]);
            if(ERR_DATA.isErrData(key)){
                return key;
            }
        }
        let htmlElement = htmlContainer.lastChild;
        let found = false;
        while(htmlElement){
            if(editHierarchy.isNodeIdElementOfParent(htmlElement)){
                found = true;
                break;
            }
            htmlElement =  htmlElement.previousSibling;
        }
        if(found === false){
            return {"unknown_error":ERR_DATA["unknown_error"]};
        }
        if(htmlElement.tagName === "DIV"){
            return key;
        }
        return htmlElement.dataset.nodeId;
    }
    /*-------------------------------------------------------------------------------------------------*/
    validate(){
        if(this.isValidated === false){
            this.value = editHierarchy.getEditHierarchyValue(this.htmlContainer);
            this.isValidated = true;
        }
        if(ERR_DATA.isErrData(this.value)){
            return false;
        }
        return true;
    }
    /*-------------------------------------------------------------------------------------------------*/
    getValue(){
        if(this.isValidated === false){
            this.value = editHierarchy.getEditHierarchyValue(this.htmlContainer);
            this.isValidated = true;
        }
        return this.value;    
    }
    /*-------------------------------------------------------------------------------------------------*/
    constructor(htmlContainer,sBaseUrl,arrHierarchy=null,options={}){
        this.htmlContainer    = htmlContainer;
        this.sBaseUrl   = sBaseUrl;
        this.options      = options;
        this.edtHrchClassName = options.hasOwnProperty("edtHrchClassName") ? options.edtHrchClassName : 'edit-hierarchy';
        htmlContainer.className = this.edtHrchClassName;
        /*Chuẩn hóa tham số*/
        let paramDataNamesDefault = {"nodeId":"node_id","title":"title","isLeaf":"is_leaf"};
        let paramUrlNamesDefault = {"nodeId":"node_id","pg":"pg","pgz":"pgz"};
        let paramDataNames = common.isEmpty(this.options)||!this.options.hasOwnProperty("paramDataNames") ? paramDataNamesDefault : this.options.paramDataNames;  
        if(!paramDataNames.hasOwnProperty("nodeId")){
            paramDataNames.nodeId = "node_id";
        }
        if(!paramDataNames.hasOwnProperty("title")){
            paramDataNames.title = "title";
        }
        if(!paramDataNames.hasOwnProperty("isLeaf")){
            paramDataNames.isLeaf = "is_leaf";
        }
        this.options.paramDataNames = paramDataNames;
        let paramUrlNames = common.isEmpty(this.options)||!this.options.hasOwnProperty("paramUrlNames") ? paramUrlNamesDefault : this.options.paramUrlNames;  
        if(!paramUrlNames.hasOwnProperty("nodeId")){
            paramUrlNames.nodeId = "node_id";
        }
        if(!paramUrlNames.hasOwnProperty("pg")){
            paramUrlNames.pg = "pg";
        }
        if(!paramUrlNames.hasOwnProperty("pgz")){
            paramUrlNames.pgz = "pgz";
        }
        this.options.paramUrlNames = paramUrlNames;
        /*End chuẩn hóa tham số*/
        this.isValidated = false;
        this.value = null;
        if(htmlContainer.querySelector("A") === null){ //nếu chưa tạo các A tag
            let fragmentATag = editHierarchy.createHtmlASequence(arrHierarchy,sBaseUrl,this.options);
            htmlContainer.appendChild(fragmentATag);
        }
        let inputDest = document.createElement("INPUT");
        inputDest.style.display = "none";
        htmlContainer.appendChild(inputDest);
        this.inputDest  = inputDest;

        let btnDelete = document.createElement("INPUT");
        btnDelete.type = "button";
        btnDelete.style.display = "none";
        htmlContainer.appendChild(btnDelete);
        this.btnDelete  = btnDelete;

        this.searchCombo = null;
        this.extAStoreData = new ExtArray(); // Chứa dữ liệu 
        this.initEvent(htmlContainer);
    }
}
/*-------------------------------------------------------------------------------------------------*/
