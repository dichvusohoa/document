/*SAU NÀY PHẢI VIẾT LẠI  2024-07-15*/
class autoForm{
    /*----------------------------------------------------------------------------------------------------*/
    /*function for autoForm object.
     * Trong function body dùng this hay autoFormObj đều được. Kiểm tra xem 1 trường có phải là trường key
     * hay không   
     * @param {string} sFieldName
     * @param {object} fieldInfo
     */
    isKeyField(sFieldName,fieldInfo){
        if( fieldInfo.hasOwnProperty(sFieldName) && 
            fieldInfo[sFieldName].hasOwnProperty("key") &&
            fieldInfo[sFieldName]["key"]){
            return true;
        } 
        else{
            return false;
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    isHiddenField(sFieldName,fieldInfo){
        if( fieldInfo.hasOwnProperty(sFieldName) && 
            fieldInfo[sFieldName].hasOwnProperty("hidden") &&
            fieldInfo[sFieldName]["hidden"]){
            return true;
        } 
        else{
            return false;
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    initHTMLForm(response){
        this.fields = response.fields;
        for(let fieldName in this.fields){
            if(this.isKeyField(fieldName,this.fields)){
                if(response.data.hasOwnProperty(fieldName)){
                    this._key.fieldName = response.data[fieldName];//2022-02-17
                }
            }
            let field = this.fields[fieldName];
            let htmlControl = this.frmControl[fieldName];
            if(!htmlControl){
                continue;
            }
            let sType =  field["control_type"];
            let sDataType = field.hasOwnProperty("data_type") ? field["data_type"] : "string";
            htmlControl.setAttribute("data_type",sDataType);//set data_type vì liên quan đến style sheet cho control
            if(sType === "combo"){
                new searchCombo(htmlControl,field.listData);
            }
            else if(sType === "date"){
                let attr = field.hasOwnProperty("format") ? {"sShowDateFormat" : field["format"]} :{"sShowDateFormat":"dd/mm/yyyy"};
                new datePicker(htmlControl,attr);
            }
            this.saveOldValue(htmlControl,response,fieldName,sType,sDataType);
        }
        this.initEvent();
    }
    /*----------------------------------------------------------------------------------------------------*/
    saveOldValue(htmlControl,response,fieldName,sType,sDataType){
        //một số trường hợp response.data không chứa dữ liệu, ví dụ form login thì response.data không có gì cả
        let value = response.data.hasOwnProperty(fieldName) ? response.data[fieldName] : null;
        let field = this.fields[fieldName];
        if(sType==="textbox"){
            if(value===null){//đây là trường hợp filed DEFAULT IS NULL và khi không nhập giá trị thì có giá trị NULL
                htmlControl.oldValue = null;
                htmlControl.value = ""; //input thì không nhận NULL được phải dùng "" để thay thế
            }
            else if(sDataType === "int"){
                htmlControl.oldValue = common.isInteger(value);
                if(htmlControl.oldValue ===false){
                    htmlControl.value = value;//sai format để nguyên
                }
                else{
                    htmlControl.value = common.numberWithCommas(htmlControl.oldValue);
                }
            }
            else if(sDataType === "number"){
                let iPrecision = response.fields[fieldName].hasOwnProperty("precision") ? response.fields[fieldName]["precision"] : 0;
                htmlControl.oldValue = common.roundNumber(value,iPrecision);
                if(htmlControl.oldValue ===false){
                    htmlControl.value = value;//sai format để nguyên
                }
                else{
                    htmlControl.value = common.numberWithCommas(htmlControl.oldValue);
                }
            }
        }
        else if(sType==="combo"){
            if(value===null){
                htmlControl.oldKey = null;
                htmlControl.key = null;
                htmlControl.value = "";//vì input.value không thể đặt = null duoc
                htmlControl.oldValue = null;
            }
            else{
                htmlControl.oldKey  = value;
                htmlControl.key     = value;
                htmlControl.value    = field.listData[value];
                htmlControl.oldValue = htmlControl.value;
            }
        }
        else if(sType==="date"){
            let sShowDateFormat = field.hasOwnProperty("format") ? field["format"] : "dd/mm/yyyy";
            if(value===null){
                htmlControl.oldValue = null;
                htmlControl.value = "";// input thì không nhận NULL được phải dùng "" để thay thế
            }
            else{
                let dtValue = new Date(value);
                if(dtValue.toString()==="Invalid Date"){
                    htmlControl.value = value;//sai format thì để nguyên
                    htmlControl.oldValue = false;
                }
                else{
                    htmlControl.value = string.dateToString(dtValue,sShowDateFormat);
                    htmlControl.oldValue = string.dateToString(dtValue,"yyyy-mm-dd");
                }
            }
        }
        else if(sType==="checkbox"){
            htmlControl.checked = value*1.0;
            htmlControl.oldValue = htmlControl.checked;
        }
        else if(sType==="radio"){
           htmlControl.checked = (value == htmlControl.value);
            //nếu control không checked thì oldValue của radio không có nghía gì (NULL)
            htmlControl.oldValue = htmlControl.checked ? htmlControl.value : null;
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    getKeyOrValue(control){
        let fields = this.fields;
        let fieldName = control.name;
        let sType = fields[fieldName]["control_type"];
        let sDataType = fields[fieldName].hasOwnProperty("data_type") ? fields[fieldName]["data_type"] : "string";
        let constraints = fields[fieldName].hasOwnProperty("constraints") ? fields[fieldName]["constraints"] : null;
        if(sType==="combo"||sType==="date"||sType==="textbox"){
            control.value = control.value.trim(); 
            if((constraints ===null || !constraints.hasOwnProperty("required") || !constraints["required"]) && control.value ===""){
                return null; //trường hợp cho phép dữ liệu null
            }
        }
        if(sType==="checkbox"){
            return control.checked;
        }
        else if(sType==="radio"){
            return control.value;
        }
        else if(sType==="combo"){
            if(!fields[fieldName].hasOwnProperty("listData")){
                return "";
            }
            let arrData = fields[fieldName]["listData"]; 
            for(let sKey in arrData){
                if(arrData[sKey]===control.value){
                    return sKey;
                }
            }
            return "";
        }
        else if(sType==="date"){
            let sShowDateFormat = fields[fieldName].hasOwnProperty("format") ? fields[fieldName]["format"] : "dd/mm/yyyy";
            return string.stringToDateStrYMD(control.value,sShowDateFormat);
        }
        else if(sType==="textbox"){ 
            if(sDataType === "int"){
                return common.isInteger(control.value);
            }
            else if(sDataType === "number"){
                let iPrecision = fields[fieldName].hasOwnProperty("precision") ? fields[fieldName]["precision"] : 0;
                return common.roundNumber(control.value,iPrecision);
            }
            else if(sDataType === "email"){
                return string.validateEmail(control.value);
            }
            else if(sDataType === "url"){
                return string.validURL(control.value);
            }
            else{
                return control.value;     
            }
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
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
    /*----------------------------------------------------------------------------------------------------*/
    isValidate(){
        let dTreeError = new ExtArray();//mô tả chi tiết, mô tả theo fieldName, mã lỗi chính, mã lỗi phụ
        for(let fieldName in this.fields){
            let field = this.fields[fieldName];
            let htmlControl = this.frmControl[fieldName];
            if(!htmlControl){
                continue;
            }
            let sType =  field["control_type"];
            let sDataType = field.hasOwnProperty("data_type") ? field["data_type"] : "string";
            if(!this.arrMustCheckControlDataType.includes(sType)){
                continue;
            }
            htmlControl.removeAttribute("err");//xóa bỏ các lỗi cũ
            //htmlControl.value = string.trim(htmlControl.value);  
            let currentValue = this.getKeyOrValue(htmlControl);
            if(currentValue===null){//htmlControl.value === "" và được phép có giá trị blank
                continue; //không kiểm tra gì nữa
            }
            //begin check lỗi kiểu dữ liệu trước   
            if(htmlControl.value !== "" && currentValue===false){
                if(sType==="date"){
                    dTreeError.setObjectValue([fieldName,"date",""],"","unique_array");
                }
                else if(sType==="textbox"){
                    if(sDataType === "int" || sDataType === "number" || sDataType === "email" || sDataType === "url"){
                        dTreeError.setObjectValue([fieldName,sDataType,""],"","unique_array");
                    }
                }
                continue;//Hễ có lỗi về kiểu thì thôi không check lỗi contrains nữa. Để đảm bảo một field tại một thời điểm chỉ có 1 lỗi
            }
            //end check lỗi kiểu dữ liệu trước 
            //begin check lỗi constraint
            if(!field.hasOwnProperty("constraints")){
                continue;
            }
            let constraints = field["constraints"];
            let sInfo;//các lỗi như required,must_be_in_list thì không có mã lỗi phụ, sInfo = "" 
            //ta sử dụng cấu trúc if/else vì 1 field có thể có nhiều lỗi nhưng hễ gặp 1 lỗi thì báo lỗi trên field đó và không càn xét các error khác nữa
            if(constraints.hasOwnProperty("required") && constraints["required"] && htmlControl.value === ""){
                sInfo = constraints["required"];  
                dTreeError.setObjectValue([fieldName,"required",sInfo],"","unique_array");  
            }
            else if(constraints.hasOwnProperty("must_be_in_list") && constraints["must_be_in_list"] && currentValue===""){ 
                sInfo = constraints["must_be_in_list"];  
                dTreeError.setObjectValue([fieldName,"must_be_in_list",sInfo],"","unique_array"); 
            }
            else{//xét các contrains ge,le,g,l
                let objContrains = {"ge":">=","le":"<=","g":">","l":"<"};
                for(let sOperator in objContrains){
                    if(constraints.hasOwnProperty(sOperator)){
                        sInfo = constraints[sOperator];
                        let check;
                        if(sDataType === "int" || sDataType === "number"){
                            eval("check = (currentValue "+objContrains[sOperator]+sInfo+");");
                        }
                        else{
                            eval("check = (currentValue "+objContrains[sOperator]+"'"+sInfo+"');");
                        }
                        if(!check){
                            dTreeError.setObjectValue([fieldName,sOperator,sInfo],"","unique_array");    
                            break;//hễ có 1 lỗi thì thôi không cần for tiếp nữa
                        } 
                    }
                }
            }
        }
        return dTreeError.data;
    }
    /*----------------------------------------------------------------------------------------------------*/
    initEvent(){
        let autoFormObj = this;//dùng autoFormObj vì trong 1 số anonymous function nghĩa của từ this đã thay đổi 
        let submitInputs = this.frmControl.querySelectorAll("input[type=submit]");
        let nSubmitInput = submitInputs.length;
        if(nSubmitInput>1){//nhiều nút Submit, chặn Enter để buộc người dùng phải click button để phân biệt Action
             this.frmControl.addEventListener("click",function(event){
                event = event ? event : window.event;
                if(event.target.tagName === "INPUT" && event.target.type === "submit" ){
                    //autoFormObj.frmControl.submitType = event.target.name;  
                    autoFormObj.submitType = event.target.name;  
                }
            });
        }
        else if(nSubmitInput===1){
            //this.frmControl.submitType = submitInputs[0].name;  
            this.submitType = submitInputs[0].name;  
        }
        else {//không có nút Submit, submit = Enter vào control
            //this.frmControl.submitType = "update";//mặc định
            this.submitType = "update";//mặc định
        }
        this.frmControl.addEventListener("keydown",function(event){
            autoFormObj.keyDown(event,nSubmitInput);//dùng anonymous function để thêm tham số vào event listener
        });
        this.frmControl.addEventListener("submit",function(event){
            event.preventDefault();
            autoFormObj.submit(event);
        });
    }
    /*----------------------------------------------------------------------------------------------------*/
    keyDown(event,nSubmitInput){
        let autoFormObj = this;
        event = event ? event : window.event;
        let keyCode = event.which || event.keyCode;
        if(nSubmitInput>1 && event.target.tagName === "INPUT" && keyCode ===13){
            //nhiều nút Submit, chặn Enter để buộc người dùng phải click button để phân biệt Action
            event.preventDefault();
        }
        if(keyCode === 83 && event.ctrlKey ) {//Ctrl+S
            event.preventDefault();
            autoFormObj.submit(event);
            return false;
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    async loadData(){
        common.loadingStatus(this.frmControl,true,"Đang tải dữ liệu"); 
        let autoFormObj = this;
        try{
            let response = await fetch(this.sUrlGet);
            if(response.status !==200){
                throw Error (response.status + ". " + response.statusText);
            }
            let jsonRespData = await response.json();
            let sNode = autoFormObj.objAtbts.hasOwnProperty("pathData") ? autoFormObj.objAtbts["pathData"] : "";
            if(sNode !==""){
                //chạy chỉ để kiểm soát lỗi
                common.showUIAndControlError(autoFormObj.tableData,jsonRespData,
                    function(){
                        //nếu không có lỗi thì không làm gì cả 
                    },
                    null, //dùng xử lý ngầm định show info lỗi lên control
                    true // throw Error để không chạy các lệnh dưới
                );
                common.showUIAndControlError(autoFormObj.tableData,jsonRespData[sNode],function(){
                        autoFormObj.initHTMLForm(jsonRespData[sNode]);
                });
            }
            else{
                common.showUIAndControlError(autoFormObj.frmControl,jsonRespData,
                    function(){autoFormObj.initHTMLForm(jsonRespData);}
                );
            }
        }
        catch(error){
            console.log(error);
        }
        finally{
            common.loadingStatus(this.frmControl,false);
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    async postData(data){
        common.loadingStatus(this.frmControl,true,"Đang upload dữ liệu");
        let autoFormObj = this;
        let sSubmitType = data["submit_type"];
        let sUrlPost = common.setURLParam(this.sUrlGet,"action",sSubmitType);
        sUrlPost = common.setURLParam(sUrlPost,"request_type","ajax");
        try{
            //let response = await fetch(this.sUrlPost,{
            let response = await fetch(sUrlPost,{
                method : "POST",
                headers: {
                    "Content-Type": "application/json",  // sent request
                    "Accept": "application/json"   // expected data sent back
                },
                body : JSON.stringify(data)
            });
            if(response.status !==200){
                throw Error (response.status + ". " + response.statusText);
            }
            let jsonRespData = await response.json();
            common.showUIAndControlError(autoFormObj.frmControl,jsonRespData,
                function(){ 
                    if(sSubmitType === "verify"){//có redirect nếu thành công
                        window.location.href = jsonRespData.info.redirectTo;
                    }
                    else{
                        //chưa có phương án
                    }
                },function(){ 
                    autoFormObj.showError(jsonRespData);
                    let imgSercurity = autoFormObj.frmControl.querySelector("IMG[security]");
                    if(imgSercurity){//refesh lại ảnh bảo mật
                        imgSercurity.src = imgSercurity.src;
                    }
                } //dừng không chạy tiếp phần redirect đằng sau nữa
            );
        }
        catch(error){
            console.log(error);
        }
        finally{
            common.loadingStatus(this.frmControl,false);
        }
        
    }
    /*----------------------------------------------------------------------------------------------------*/
    getNewData(){
        let newElement = {};
        for(let fieldName in this.fields){
            let field = this.fields[fieldName];
            let htmlControl = this.frmControl[fieldName];
            if(!htmlControl){
                continue;
            }
            let sType =  field["control_type"];
            if(sType !== "radio" || (sType === "radio" && htmlControl.checked) ){//loại trừ các radio khong checked
                newElement[fieldName] = this.getKeyOrValue(htmlControl);
            }
        }
        return newElement;
    };
    /*----------------------------------------------------------------------------------------------------*/
    getUpdateData(){
        let updateElement = {};
        for(let fieldName in this.fields){
            let field = this.fields[fieldName];
            let htmlControl = this.frmControl[fieldName];
            if(!htmlControl){
                continue;
            }
            let sType =  field["control_type"];
            if(sType !== "radio" || (sType === "radio" && htmlControl.checked) ){//loại trừ các radio khong checked
                let currentValue = this.getKeyOrValue(htmlControl);
                if(sType==="combo"){
                    if(htmlControl.oldKey != currentValue){
                        updateElement[fieldName]= currentValue;   
                    }
                }
                else if(htmlControl.oldValue != currentValue){
                    updateElement[fieldName]= currentValue;
                } 
            }
        }
        //updateElement["_key"] = this.frmControl["_key"].value;
        updateElement["_key"] = this._key;
        return updateElement;
        
    }
     /*----------------------------------------------------------------------------------------------------*/
    submit(event){
        let autoFormObj = this;
        if(autoFormObj.submitType==="delete"){
            if(confirm("Một số dữ liệu sẽ bị xóa. Bạn thực sự muốn làm điều này?")===false){
                return;
            } 
        }
        else{
            let errInfo = this.isValidate();
            if(!common.isEmpty(errInfo)){
                let err = {"status":ERR_STATUS.client_error,"info":errInfo,"extra":""};
                this.showError(err);
                return;
            }
        }
        let postData = {};
        //if(this.frmControl.submitType==="delete"){
        if(this.submitType === "delete"){    
            //postData["_key"] = this.frmControl["_key"].value;
            postData["_key"] = this._key;
        }
       // else if(this.frmControl["_key"].value ===""){
        else if(common.isEmpty(this._key)){
            postData = this.getNewData();
        }
        else{
            postData = this.getUpdateData();
        }
        if(common.isEmpty(postData)){
            return;
        }
        //postData["submit_type"] = this.frmControl.submitType;
        postData["submit_type"] = this.submitType;
        this.postData(postData);
    };
    /*----------------------------------------------------------------------------------------------------*/
    constructor(frmControl,sUrlGet,objAtbts={}){
        //var autoFormObj = this;//một số trường hợp không dùng từ khóa this được thì phải dùng qua autoFormObj
        this.frmControl         = frmControl;
        //this.frmControl.submitType = "";//xác định nút Submit nào được ấn 
        this.submitType = "";//xác định nút Submit nào được ấn 
        //Các kiểu control chứa dữ liệu phải kiểm tra
        this.arrMustCheckControlDataType = ["combo","date","textbox"];
        this.sUrlGet            = sUrlGet;
       // this.sUrlPost           = sUrlPost;
        this.objAtbts            = objAtbts;
        //this.fields             = [];
        this.fields             = {};
        this._key               = {};
    }
}