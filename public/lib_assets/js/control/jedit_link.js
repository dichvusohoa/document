class editLink{
    /*-------------------------------------------------------------------------------------------------*/
    /*jsonData có cấu trúc {"text":"something text", "address":"something address" }*/
    static createHtmlA(jsonData){
        let htmlA = document.createElement("A");
        if(jsonData === null){
            htmlA.innerHTML = "...";
            htmlA.href = "#";
            return htmlA;
        }
        htmlA.innerHTML = jsonData.hasOwnProperty("text") ? jsonData["text"] : "...";
        htmlA.href = jsonData.hasOwnProperty("address") ? jsonData["address"] : "";
        return htmlA;
    }    
    /*-------------------------------------------------------------------------------------------------*/
    /*reference https://www.geeksforgeeks.org/what-is-the-default-value-of-the-display-property-in-css/
     */
    formCancel(){
        this.frmControl.style.display = "none";
        let htmlA = this.htmlContainer.querySelector("A");
        htmlA.style.display = "";
        htmlA.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    getKeyOrValue(control){
        let fields = this.fields;
        let fieldName = control.name;
        let sDataType = fields[fieldName].hasOwnProperty("data_type") ? fields[fieldName]["data_type"] : "string";
        let constraints = fields[fieldName].hasOwnProperty("constraints") ? fields[fieldName]["constraints"] : null;
        control.value = control.value.trim();
        if((constraints ===null || !constraints.hasOwnProperty("required")) && control.value ===""){
            return null; //trường hợp cho phép dữ liệu null
        }
        if(sDataType === "email"){
            return string.validateEmail(control.value);
        }
        else if(sDataType === "url"){
            return string.validURL(control.value);
        }
        else{
            return control.value;     
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    showError(objErr){
        let autoFormObj = this; // dùng autoFormObj an toàn hơn vì 1 số vòng lặp và function inner(ví dụ processLeaf) trong function, this có thể đổi ngữ nghĩa
        let frmControl   = this.frmControl;
        let divMessage   =  frmControl.querySelector("div.message"); 
        divMessage.innerHTML="";
        if(objErr.hasOwnProperty("extra") && objErr.extra !==""){
            divMessage.innerHTML="<p>"+objErr.extra+"</p>";
        }
        if(objErr.status===ERR_STATUS.client_ok||objErr.status===ERR_STATUS.server_ok||objErr.status===ERR_STATUS.server_logic_error){
            divMessage.removeAttribute("error");
        }
        else{//status client_error, server_error;
            divMessage.setAttribute("error","");
        }  
        if(common.isEmpty(objErr.info)){
            return objErr.status;
        }
        let extAErrDetail = new ExtArray();
        extAErrDetail.data = objErr.info;
        let sSummaryErr = divMessage.innerHTML;
        extAErrDetail.processLeaf = function(arrChain,sDescription){
            let sFieldName  = arrChain[0];
            let sErrCode     = arrChain[1];
            let sErrSubCode  = arrChain[2];
            //let htmlInput     = htmlTBody.querySelector("tr[name='"+sRowId+"']");
            let htmlControl     = false;
            let sAttrErrCode = sErrSubCode==="" ? "err--" + sErrCode : "err--" + sErrCode + "--" + sErrSubCode;
            sAttrErrCode = sAttrErrCode.replaceAll("_", "-"); // Thay _ thành - vì trong CSS dùng dấu - và --
            if(sFieldName === "*"){//lỗi toàn bộ form
                //for(let sName in this.fields){
                for(let sName in autoFormObj.fields){    
                    htmlControl = autoFormObj.frmControl[sName];
                    if(htmlControl){
                        htmlControl.setAttribute("err",sAttrErrCode);
                    }
                }
            }
            else{
                htmlControl     = autoFormObj.frmControl[sFieldName];
                if(htmlControl){
                    htmlControl.setAttribute("err",sAttrErrCode);
                }
            }
            if(sDescription ===null){
                return;//không hiện thông báo lỗi
            }
            else if(sDescription===""){
                sDescription = ERR_DATA.getErrDescription(sErrCode,sErrSubCode);
            }
            else{
                //lỗi tùy chọn. Hiện nay không xử lý gì để hiển thị tự nhiên các
                //custom sDescription dạng string do server trả về. Chưa có các dạng lỗi phức tạp
            }
            if(autoFormObj.fields[sFieldName].hasOwnProperty("title")){
                sDescription =autoFormObj.fields[sFieldName]["title"] + " " + sDescription;
            }
           // let sAttrErrCode = sErrSubCode==="" ? "err--" + sErrCode : "err--" + sErrCode + "--" + sErrSubCode;
            sSummaryErr = sSummaryErr + "<p><img alt=\"\" err=\""+sAttrErrCode+"\">"+sDescription+"</p>";
        };
        extAErrDetail.browseTree(extAErrDetail.data,[]);
        if(sSummaryErr!==""){
            divMessage.innerHTML = sSummaryErr;
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    isValidate(){
        let dTreeError = new ExtArray();//mô tả chi tiết, mô tả theo fieldName, mã lỗi chính, mã lỗi phụ
        for(let fieldName in this.fields){
            let field = this.fields[fieldName];
            let htmlControl = this.frmControl[fieldName];
            let sDataType = field.hasOwnProperty("data_type") ? field["data_type"] : "string";
            htmlControl.removeAttribute("err");//xóa bỏ các lỗi cũ
            let currentValue = this.getKeyOrValue(htmlControl);
            if(currentValue===null){//htmlControl.value === "" và được phép có giá trị blank
                continue; //không kiểm tra gì nữa
            }
            //begin check lỗi kiểu dữ liệu trước   
            if(htmlControl.value !== "" && currentValue===false){
                //htmlControl có data_type là email hoặc url
                dTreeError.setObjectValue([fieldName,sDataType,""],"","unique_array");
                continue;//Hễ có lỗi về kiểu thì thôi không check lỗi contrains nữa. Để đảm bảo một field tại một thời điểm chỉ có 1 lỗi
            }
            //end check lỗi kiểu dữ liệu trước 
            //begin check lỗi constraint
            if(!field.hasOwnProperty("constraints")){
                continue;
            }
            let constraints = field["constraints"];
            let sInfo;//các lỗi như required,must_be_in_list thì không có mã lỗi phụ, sInfo = "" 
            if(constraints.hasOwnProperty("required") && htmlControl.value===""){
                sInfo = constraints["required"];  
                dTreeError.setObjectValue([fieldName,"required",sInfo],"","unique_array");  
            }
        } 
        return dTreeError.data;
    }
    /*-------------------------------------------------------------------------------------------------*/
    formOK(){
        let errInfo = this.isValidate();
        if(!common.isEmpty(errInfo)){
            let err = {"status":ERR_STATUS.client_error,"info":errInfo,"extra":""};
            this.showError(err);
            return;
        }
        this.frmControl.style.display = "none";
        let htmlA = this.htmlContainer.querySelector("A");
        htmlA.style.display = "inline";
        htmlA.innerHTML = this.frmControl.text.value;
        htmlA.href = this.frmControl.address.value;
        htmlA.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    htmlAClick(htmlA){
       htmlA.style.display = "none";
      // this.frmControl.style.width = `calc(${this.htmlContainer.offsetWidth}px + 8rem)`;
       this.frmControl.text.value = htmlA.innerHTML;
       this.frmControl.address.value = htmlA.href;
       this.frmControl.style.display = "inline-flex";
       this.frmControl.elements["text"].focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    click =(e)=>{
        e = e ? e : window.event;
        let control = e.target;
        if(control.tagName === "A"){
            e.preventDefault();
        }
        if(control.tagName === "A"){
            this.htmlAClick(control);
        }
        else if(control === this.frmControl.btnOK){
            this.formOK();
        }
        else if(control === this.frmControl.btnCancel){
            this.formCancel();
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDown=(event)=>{
        let keyCode = event.key;
        if(keyCode === "Enter"){//Enter
            this.formOK();
        } 
        else if(keyCode === "Escape"){//ESC
            this.formCancel();
        } 
    }
    /*-------------------------------------------------------------------------------------------------*/
    initEvent(htmlContainer){
        htmlContainer.addEventListener("click",this.click);
        htmlContainer.addEventListener("keydown",this.keyDown); 
    }
    /*-------------------------------------------------------------------------------------------------*/
    createFormData(){
        let frmControl = document.createElement("FORM");
        
        let btnCancel = document.createElement("INPUT");
        btnCancel.name = "btnCancel";
        btnCancel.type = "button";
        btnCancel.className = "btn-close";
        frmControl.appendChild(btnCancel);
        
        let lblText = document.createElement("LABEL");
        lblText.innerHTML = "Hiển thị";
        let inputText = document.createElement("INPUT");
        inputText.name = "text";
        
        let lblAddress = document.createElement("LABEL");
        lblAddress.innerHTML = "Địa chỉ";
        let inputAddress = document.createElement("INPUT");
        inputAddress.name = "address";
    
        frmControl.appendChild(lblText);
        frmControl.appendChild(inputText);
        frmControl.appendChild(lblAddress);
        frmControl.appendChild(inputAddress);
    
        let btnOK = document.createElement("INPUT");
        btnOK.name = "btnOK";
        btnOK.type = "button";
        btnOK.value = "OK";
        btnOK.className = "btn-autox30 btn-autox30--ok";
        frmControl.appendChild(btnOK);
        
        let divMessage = document.createElement("DIV");
        divMessage.className = "message";
        frmControl.appendChild(divMessage);
        
        this.htmlContainer.appendChild(frmControl);
        return frmControl;
    }
    /*-------------------------------------------------------------------------------------------------*/
    constructor(htmlContainer,jsonData=null,sLinkType="url",required=false){
        this.htmlContainer    = htmlContainer;
        this.htmlContainer.className = "edit-link";
        if(htmlContainer.querySelector("A") === null){ //nếu chưa tạo  A tag
            let htmlA = editLink.createHtmlA(jsonData);
            htmlContainer.appendChild(htmlA);
        }
        this.frmControl = this.createFormData();
        this.fields = {"text":{"data_type":"string"},"address":{}};
        if(sLinkType === "url"){
            this.fields.address.data_type = "url";
        }
        else if(sLinkType === "email"){
            this.fields.address.data_type = "email";
        }
        if(required){
            this.fields.text.constraints = {"required":"true"};
            this.fields.address.constraints = {"required":"true"};
        }
        this.initEvent(htmlContainer);
    }
}
/*-------------------------------------------------------------------------------------------------*/
