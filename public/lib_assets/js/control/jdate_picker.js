/*Cấu trúc html của datepicker
 * <div class="date_picker">
    *  <input date_type="date">
    *  <input type=button>
 *     <div>
 *          <p>
 *              <input name = prev-month><label>Tháng</label><input name="month" type="number" min="1" max="12">
 *              <input name="year" type="number" min="1990" max="2050">
 *              <input name="next-month" type="button" value="▶">
 *          </p>    
*           <span max="2050">CN</span>
*           <span max="2050">T2</span>
*           <span max="2050">T3</span>
*           <span max="2050">T5</span>
*           <span max="2050">T6</span>
*           <span max="2050">T7</span>
*           <input type="button" class="btnDate" value="30"  data-date="2023-07-30" style="height: 13px;">
*              ....
*              <!-- Các nut date ngày trong tháng-->
 *           <p>
 *              <button name = clear></button>
 *              <button name = today></button>
 *           </p>
 *     </div> 
 * <div>
 */
class datePicker{
    static arrWeek = ["CN","T2","T3","T4","T5","T6","T7"];
    /*-------------------------------------------------------------------------------------------------*/
    createCalendar(dtConsideringDate){
        const iLastDayOfMonth = common.getLastDayOfMonth(dtConsideringDate).setHours(0,0,0,0);
        const iToday = new Date().setHours(0,0,0,0);
        const iConsideringDate = new Date(dtConsideringDate).setHours(0,0,0,0); //tránh gây ảnh hưởng đến dtConsideringDate
        let iCurrDate = null;
        let iB,iE;
        if(this.isDateFirst){
            iE = this.sShowDateFormat.length;
            iCurrDate = string.stringToDate(this.inputDest.value.substring(0,iE),this.sShowDateFormat);
        }
        else{
            iB = this.inputDest.value.length - this.sShowDateFormat.length;
            iCurrDate = string.stringToDate(this.inputDest.value.substring(iB),this.sShowDateFormat);
        }
        if(iCurrDate !== false){
            iCurrDate = iCurrDate.setHours(0,0,0,0);
        }
        let iConsideringMonth = dtConsideringDate.getMonth();
        let iPreviousMonth = (iConsideringMonth + 11) % 12; // Tự động quay vòng về tháng 12
        let iNextMonth = (iConsideringMonth + 1) % 12; // Tự động quay vòng về tháng 1
        let iConsideringYear = dtConsideringDate.getFullYear();
        //ngày mùng 1 đâu tiên của tháng
        let dtFirstDayOfMonth = common.getFirstDayOfMonth(dtConsideringDate);
        //ngày chủ nhật đầu tiên trên lịch, vì ngày đầu tháng có thể không phải Chủ nhật
        let dtStartDate = new Date(dtFirstDayOfMonth.getTime() - 24*3600000*dtFirstDayOfMonth.getDay());
        //Số ngày của tháng
        let iNumDays = common.getNumDaysOfMonth(dtConsideringDate);
        iNumDays = 7*Math.ceil((iNumDays + dtFirstDayOfMonth.getDay())/7);
        common.removeAllChild(this.divCalendar); 
        let p1 = document.createElement("P");
        this.divCalendar.appendChild(p1);
        
        let inputPrevMonth = document.createElement("INPUT");
        inputPrevMonth.name = "prev-month";
        inputPrevMonth.type = "button";
        inputPrevMonth.value = "◀";
        inputPrevMonth.setAttribute("accesskey","p");
        p1.appendChild(inputPrevMonth);
        
        let label1 = document.createElement("LABEL");
        label1.innerHTML = "Tháng";
        p1.appendChild(label1);
        
        let inputMonth = document.createElement("INPUT");
        inputMonth.name = "month";
        inputMonth.type = "number";
        inputMonth.value = iConsideringMonth+1;
        inputMonth.setAttribute("min",1);
        inputMonth.setAttribute("max",12);
        inputMonth.setAttribute("accesskey","m");
        p1.appendChild(inputMonth);
        
        let inputYear = document.createElement("INPUT");
        inputYear.name = "year";
        inputYear.type = "number";
        inputYear.value = iConsideringYear;
        inputYear.setAttribute("min",0);
        inputYear.setAttribute("max",9999);
        inputYear.setAttribute("accesskey","y");
        p1.appendChild(inputYear);
        
        let inputNextMonth = document.createElement("INPUT");
        inputNextMonth.name = "next-month";
        inputNextMonth.type = "button";
        inputNextMonth.value = "▶";
        inputNextMonth.setAttribute("accesskey","n");
        p1.appendChild(inputNextMonth);
        
        for (let i = 0; i < datePicker.arrWeek.length; i++) {
            let spanDay = document.createElement("SPAN");
            spanDay.innerHTML = datePicker.arrWeek[i];
            spanDay.setAttribute("max",2050);
            this.divCalendar.appendChild(spanDay);
        }
        let dtDate = dtStartDate;
        this.sStartDate = string.dateToString(dtStartDate,"yyyy-mm-dd");
        for(let i = 0; i<iNumDays; i++){
            let iDate = dtDate.setHours(0,0,0,0);
            let inputDate = document.createElement("INPUT");
            inputDate.type = "button";
            inputDate.className = "btnDate";
            inputDate.value = dtDate.getDate();
            inputDate.style.height =  (this.itemDateHeight === 0 || this.itemDateHeight === "0" || this.itemDateHeight === "0px") ? "calc(0.13*"+this.divCalendar.clientWidth+"px)" : this.itemDateHeight;
            
            if(dtDate.getMonth() === iPreviousMonth ){
                inputDate.setAttribute("prev-month","true");
            }
            else if(dtDate.getMonth() === iNextMonth ){
                inputDate.setAttribute("next-month","true");
            }
            else if(iDate=== iLastDayOfMonth){
                inputDate.setAttribute("last-day-of-month","true");
            }
            
            if (i % 7 === 6){
                inputDate.setAttribute("last-column","true");
            }
            if (i>=iNumDays-7){
                inputDate.setAttribute("last-row","true");
            }
            if(iDate === iToday){
                inputDate.setAttribute("today","true");
            }
            if(iDate === iConsideringDate){
                inputDate.setAttribute("considering","true");
            }
            if(iCurrDate !== false && iDate === iCurrDate){
                inputDate.setAttribute("current","true");
            }
            let sDateYMD  = string.dateToString(dtDate,"yyyy-mm-dd");
            inputDate.setAttribute("data-date",sDateYMD);
            this.divCalendar.appendChild(inputDate);
            dtDate.setDate(dtDate.getDate()+1);
        }
        dtDate.setDate(dtDate.getDate() - 1);
        this.sEndDate = string.dateToString(dtDate,"yyyy-mm-dd");
        
        let p2 = document.createElement("P");
        this.divCalendar.appendChild(p2);
        let btnClear = document.createElement("BUTTON");
        btnClear.name = "clear";
        btnClear.type = "button";
        btnClear.innerHTML  = "<u>X</u>óa";
        btnClear.setAttribute("accesskey","x");
        p2.appendChild(btnClear);
        let btnToday = document.createElement("BUTTON");
        btnToday.name = "today";
        btnToday.type = "button";
        btnToday.innerHTML  = "<u>H</u>ôm nay";
        btnToday.setAttribute("accesskey","h");
        p2.appendChild(btnToday);
        
        let datePkObj = this;
        //console.log(datePkObj.divCalendar.querySelector("input[current]"));
        setTimeout(function(){ 
            datePkObj.divCalendar.querySelector("input[considering]").focus();
        },10);
       // let xx = this.divCalendar.querySelector("input[considering]");
        //this.divCalendar.querySelector("input[considering]").focus();
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event hander cho event click của inputDest */  
    //this.inputArrowClick= function (event){
    showHideCalendar(){
        let inputDest =  this.inputDest;
        let divCalendar = this.divCalendar;
        if(divCalendar.style.display === 'flex'){
            divCalendar.style.display = 'none';    
            inputDest.focus(); 
            return;  
        }
        let iE,iB,dtCurrentDate;
        if(this.isDateFirst){
            iE = this.sShowDateFormat.length;
            dtCurrentDate = string.stringToDate(this.inputDest.value.substring(0,iE),this.sShowDateFormat);
        }
        else{
            iB = this.inputDest.value.length - this.sShowDateFormat.length;
            dtCurrentDate = string.stringToDate(this.inputDest.value.substring(iB),this.sShowDateFormat);
        }
        if(dtCurrentDate === false){
            dtCurrentDate = new Date();
        }
        divCalendar.style.display = 'flex';  
        this.createCalendar(dtCurrentDate);
    };
    /*-------------------------------------------------------------------------------------------------*/
    keyDownInputDestDelete(event,idxToken){
        let sValue = this.inputDest.value;
        if(this.inputDest.selectionStart === 0 && this.inputDest.selectionEnd === this.sShowDateTimeFormat.length){
            //đây là trường hợp select all và xóa hết
            this.inputDest.value = this.sShowDateTimeFormat;
            this.selectTextByTokenIdx(0);
        }
        else{
            let token = this.dateTimeFormatTokens[idxToken];
            let sValue = this.inputDest.value;
            this.inputDest.value = 
            sValue.substring(0,token["pos"]) + token["token"] + sValue.substring(token["pos"] + token["length"]);
            this.selectTextByTokenIdx(idxToken);
        }
        this.bufferNumber = "";//khi ấn xóa thì buffer cũng xóa
        this.handleDateTimeChange(sValue);
        event.preventDefault();//chặn việc xóa ngầm định
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownInputDestNumber(event,idxToken){
        let token = this.dateTimeFormatTokens[idxToken];
        if(token["type"] === "separator"){
            return;
        }
        let sValue = this.inputDest.value;
        if(this.bufferNumber.length >= token["length"]){
            this.bufferNumber = event.key;
        }
        else{
            this.bufferNumber = this.bufferNumber + event.key;
        }
        //loại bỏ nhứng số 0 dư thừa phía trước vì đề phòng người dùng gõ số 0 đầu tiên
        this.bufferNumber = parseInt(this.bufferNumber).toString();
        let sReplace = this.bufferNumber;
        let isToNextToken = false;
        let fVal = Number.parseInt(sReplace); 
        if(fVal >= token["max"]){
            sReplace = token["max"];
            isToNextToken = true;
        }
        else if(fVal*10 >= token["max"]){ //gõ thêm một chữ số bất kỳ nữa là vượt quá giá trị max
            isToNextToken = true;
        }
        if(sReplace.length < token["length"]){
            sReplace = sReplace.padStart(token["length"],'0');
        }
        this.inputDest.value = 
        sValue.substring(0,token["pos"]) + sReplace + sValue.substring(token["pos"] + token["length"]);
        if(isToNextToken){
            idxToken = this.nextOrPrevToDateTimeToken(idxToken,true);
        }
        if(idxToken!==null){
            this.selectTextByTokenIdx(idxToken);
        }
        this.handleDateTimeChange(sValue);
        event.preventDefault();//chặn viẹc tạo ký tự ngầm định
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownInputDestUpDown(event,idxToken){
        let sValue = this.inputDest.value;
        let token = this.dateTimeFormatTokens[idxToken];
        if(!token || token["type"] === "separator"){
            return;
        }
        let sReplace = this.inputDest.value.substring(token["pos"],token["pos"] + token["length"]);
        let fVal = parseInt(sReplace, 10);
        if(Number.isNaN(fVal)){
            fVal = 0;
        }
        else if(event.key === "ArrowUp"){
            fVal = fVal + 1;
        }
        else{
            fVal = fVal - 1;
        }
        if(fVal < token["min"]){
            fVal = token["min"];
        }
        else if(fVal > token["max"]){
            fVal = token["max"];
        }
        sReplace = fVal.toString().padStart(token["length"],'0');
        this.inputDest.value = 
        sValue.substring(0,token["pos"]) + sReplace + sValue.substring(token["pos"] + token["length"]);
        this.selectTextByTokenIdx(idxToken);
        this.handleDateTimeChange(sValue);
        event.preventDefault();//vì lý do mặc định sẽ dịch chuyển con trỏ nên phải chặn lại để giữ nguyên tại token hiện thời
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownInputDestCtr(event){
        if(event.key === "ArrowUp" || event.key === "ArrowDown"){
            if(this.divCalendar){
                this.showHideCalendar();
            }    
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownBtnDate(event){
        let arrowKeys = ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"];
        let keyCode = event.key;
        if(!arrowKeys.includes(keyCode)){
            return; 
        }
        //chỉ xử lý các key trong arrowKeys
        let btnCsdrDate = this.divCalendar.querySelector("input.btnDate[considering]");
        if(btnCsdrDate === null){
            return;
        }
        let iOffsetDay = 0;
        if(keyCode === "ArrowLeft"){
            iOffsetDay = - 1;
        }
        else if(keyCode === "ArrowUp"){
            iOffsetDay = - 7;
        }
        else if(keyCode === "ArrowRight"){
            iOffsetDay = 1;
        }
        else if(keyCode === "ArrowDown"){
            iOffsetDay = 7;
        }
        let dtDate = new Date(btnCsdrDate.getAttribute("data-date"));
        dtDate = common.dayAfterOffset(dtDate,iOffsetDay);
        let sDate = string.dateToString(dtDate,"yyyy-mm-dd");
        if(sDate < this.sStartDate || sDate > this.sEndDate){
            this.createCalendar(dtDate);
        }
        else{
            let inputDate = this.divCalendar.querySelector(`input[data-date="${sDate}"`);
            inputDate.focus();
        }
        //để chặn việc dịch chuyển scroollBar của trình duyệt theo mặc định, có thể làm đóng mở divCalendar
        event.preventDefault();
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownInputDest(event){
        let caretPos = this.inputDest.selectionStart;
        let idx = this.getTokenIdxDateFormat(caretPos);
        if(event.key === "Enter"){//Giả lập event change
            this.triggerInputCommit("keyenter");
        }
        else if(event.key.match(/\d/)){
            this.keyDownInputDestNumber(event,idx);
        }
        else if(event.key === "Backspace" || event.key === "Delete"){
            this.keyDownInputDestDelete(event,idx);
        }
        else if(event.key === "ArrowLeft" || event.key === "ArrowRight"){
            //dịch chuyển carret sang trái phải sang cho tới vị trí token là thời gian (tránh các dấu phân cách)
            /*if (this.inputArrow && idx === this.dateTimeFormatTokens.length -1 && event.key === "ArrowRight"){
                this.inputArrow.focus();
                return;
            }*/
            idx = this.nextOrPrevToDateTimeToken(idx,event.key !== "ArrowLeft");
            if(idx === null){
                return;
            }
            this.selectTextByTokenIdx(idx);
            //chặn hành vi di chuyển mặc định nếu không con trỏ sẽ dịch chuyển nhiều token
            event.preventDefault();
        }
        else if(event.key === "ArrowUp" || event.key === "ArrowDown"){
            if ((event.ctrlKey || event.metaKey) && this.divCalendar ){
                this.keyDownInputDestCtr(event);
            }
            else{
                this.keyDownInputDestUpDown(event,idx);
            }
        }
        else if( !(event.key === "Tab" || event.ctrlKey || event.metaKey || event.shiftKey)){
            //nếu ngoài các phím tab, số, backspace, delete, arrow ra mà không ấn CTRL, SHIFT thì không cho phép
            event.preventDefault();
        } 
    }
    /*-------------------------------------------------------------------------------------------------*/
    changeMonthOrYear(event){
        let iNewVal = common.isInteger(event.target.value);
        if(iNewVal === false){
            return;
        }
        let iMin = Number.parseInt(event.target.getAttribute("min"));
        let iMax = Number.parseInt(event.target.getAttribute("max"));
        if(iNewVal < iMin){
            iNewVal = iMin;
        }
        else if(iNewVal > iMax){
            iNewVal = iMax;
        }
        let iYear = this.divCalendar.querySelector('input[name="year"]').value;
        let iMonth = this.divCalendar.querySelector('input[name="month"]').value -1;
        let btnConsidering = this.divCalendar.querySelector("input[considering]");
        let iDay = 1;
        if(btnConsidering){
            iDay = btnConsidering.value;
        }
        if(event.target.name === "month"){
            iMonth = iNewVal - 1;
        }
        else if(event.target.name === "year"){
            iYear = iNewVal;
        }
        let dtDate = new Date(iYear, iMonth, iDay);
        this.createCalendar(dtDate);
    }
    /*-------------------------------------------------------------------------------------------------*/
    changeInputDest(){
        console.log("88888888");
    }
    /*-------------------------------------------------------------------------------------------------*/
    selectDate(btnDate){
        let sValue = this.inputDest.value;
        let dt1 = new Date(btnDate.getAttribute("data-date"));
        let sYMD = string.dateToString(dt1,this.sShowDateFormat);//tạo phần chuỗi thời gian YMD
        
        if(this.isDateFirst){
            this.inputDest.value = sYMD + sValue.substring(sYMD.length);//thay thế phần đầu của inputDest.value bằng sYMD
        }
        else{
            this.inputDest.value = sValue.substring(0,sValue.length - sYMD.length) + sYMD;
        }
        this.handleDateTimeChange(sValue);
        this.triggerInputCommit("selectdate");
        this.divCalendar.style.display = "none";
        this.inputDest.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    clearInputDest(){
        this.inputDest.value = this.sShowDateTimeFormat;
        this.isValidated = false;//phải validate lại
        this.divCalendar.style.display = "none";
        this.inputDest.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    today(){
        let dtToday = new Date();
        this.inputDest.value = string.dateToString(dtToday,this.sShowDateTimeFormat);
        this.value = dtToday;
        this.isValidated = true;//không cần validate lại nữa
        this.divCalendar.style.display = "none";
        this.inputDest.focus();
    }
    /*-------------------------------------------------------------------------------------------------*/
    prevNextMonth(event){
        let iYear = this.divCalendar.querySelector('input[name="year"]').value;
        let iMonth = this.divCalendar.querySelector('input[name="month"]').value -1;
        let btnConsideringDate  = this.divCalendar.querySelector("input[considering]");
        let iDate = 1;
        if(btnConsideringDate){
            iDate = btnConsideringDate.value;
        }
        const date = new Date(iYear, iMonth, iDate); 
        let dtDate;
        if(event.target.name === "prev-month"){
            dtDate = common.previousMonth(date);
        }
        else{
            dtDate = common.nextMonth(date);
        }
        this.createCalendar(dtDate);
    }
    /*-------------------------------------------------------------------------------------------------*/
    nextOrPrevToDateTimeToken(idxCurrentToken,isNext){
        let idx = idxCurrentToken;
        do{
            idx = isNext ? idx+1 : idx-1;
            
        }while(idx >= 0 && idx < this.dateTimeFormatTokens.length && this.dateTimeFormatTokens[idx]["type"] === "separator")
        if(idx < 0){
            idx = 0;
        } 
        else if(idx >= this.dateTimeFormatTokens.length){
            idx = this.dateTimeFormatTokens.length - 1;
        }
        if(this.dateTimeFormatTokens[idx]["type"] === "datetime"){
            return idx;
        }
        else{
            return null;
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    getTokenIdxDateFormat(caretPos){
        let i= 0;
        let token = this.dateTimeFormatTokens[i];
        while(i < this.dateTimeFormatTokens.length && caretPos >= token["pos"]){
            if(caretPos >= token["pos"] && caretPos < token["pos"] + token["length"]){
                return i;
            }
            i = i +1;
            token = this.dateTimeFormatTokens[i];
        }
        return null;
    }
    /*-------------------------------------------------------------------------------------------------*/
    selectTextByTokenIdx(tokenIdx){
        let token = this.dateTimeFormatTokens[tokenIdx];
        this.inputDest.setSelectionRange(token["pos"], token["pos"] + token["length"]); // Ch
        if(this.tokenIdx !== tokenIdx){//chuyển sang token mới
            this.bufferNumber = "";
            this.tokenIdx = tokenIdx;
        }
    }
     /*-------------------------------------------------------------------------------------------------*/
    /*sử dụng trong autoTable sau này*/
    clearDatePicker(){
        let divDatePicker    =   this.divDatePicker;
        if(divDatePicker && divDatePicker.tagName === "DIV" && divDatePicker.className === this.datePickerClassName){//Đề phòng bị xóa từ trước thì không xóa lại nữa
            let parent      =   divDatePicker.parentNode;
            if(parent){
                let inputDest = this.inputDest;
                //xóa hết event handler gắn vào
                inputDest.removeEventListener("mouseup",this.mouseUp);
                inputDest.removeEventListener("select",this.select);
                inputDest.removeEventListener("cut",this.cut);
                inputDest.removeEventListener("paste",this.paste);
                parent.insertBefore(inputDest, divDatePicker); 
                parent.removeChild(divDatePicker);
            }
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*bổ sung, phân tích thêm các thành phần định dạng ngày tháng*/
    parseDateTimeFormat(){
        let re = string.splitDateTimeFormatIntoParts(this.sShowDateTimeFormat);
        this.sShowDateFormat = re.sDateFormat;
        this.sShowTimeFormat = re.sTimeFormat;
        this.isDateFirst = re.isDateFirst;
      
        const arrType = ["hh", "mm", "ss", "dd", "yyyy"];
        this.dateTimeFormatTokens = this.sShowDateTimeFormat.match(/(hh|mm|ss|dd|yyyy)|([^\w]+)/g);
        let iStart= 0;
        let isYMDPart = this.isDateFirst ? true : false; //dùng để xác định xem mm là month hay minute
        for(let i=0; i<this.dateTimeFormatTokens.length; i++){
            let sToken = this.dateTimeFormatTokens[i];
            if(!arrType.includes(sToken)){//loại separator
                isYMDPart = sToken === this.sYMDSeparator ? true : false;
            }
            let idx = this.sShowDateTimeFormat.indexOf(sToken, iStart);
            if(idx !== -1){
                this.dateTimeFormatTokens[i] = {
                    "token":sToken,
                    "pos":idx,
                    "length":this.dateTimeFormatTokens[i].length
                };
                switch (sToken) {
                    case "yyyy":
                        this.dateTimeFormatTokens[i]["meaning"] = "year";
                        this.dateTimeFormatTokens[i]["min"] = 0;
                        this.dateTimeFormatTokens[i]["max"] = 9999;
                    break;
                    case "mm":
                        if(isYMDPart){
                            this.dateTimeFormatTokens[i]["meaning"] = "month";
                            this.dateTimeFormatTokens[i]["min"] = 1;
                            this.dateTimeFormatTokens[i]["max"] = 12;
                        }
                        else{
                            this.dateTimeFormatTokens[i]["meaning"] = "minute";
                            this.dateTimeFormatTokens[i]["min"] = 0;
                            this.dateTimeFormatTokens[i]["max"] = 59;
                        }
                    break;
                    case "dd":
                        this.dateTimeFormatTokens[i]["meaning"] = "day";
                        this.dateTimeFormatTokens[i]["min"] = 1;
                        this.dateTimeFormatTokens[i]["max"] = 31;
                    break;
                    case "hh":
                        this.dateTimeFormatTokens[i]["meaning"] = "hour";
                        this.dateTimeFormatTokens[i]["min"] = 0;
                        this.dateTimeFormatTokens[i]["max"] = 23;
                    break;
                    case "ss":
                        this.dateTimeFormatTokens[i]["meaning"] = "second";
                        this.dateTimeFormatTokens[i]["min"] = 0;
                        this.dateTimeFormatTokens[i]["max"] = 59;
                    break;
                }
                if(arrType.includes(sToken)){
                    this.dateTimeFormatTokens[i]["type"] = "datetime"; 
                }
                else{
                    this.dateTimeFormatTokens[i]["type"] = "separator"; 
                }
            }
            iStart = iStart + this.dateTimeFormatTokens[i].length;
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    static getDatePickerValue(strValue,sShowDateTimeFormat,defaultValue,constraints){
        if(strValue===""){
            let isRequired = constraints && constraints.hasOwnProperty("required") && constraints["required"];
            if(!isRequired){
                return null;
            }
            else if(defaultValue){
                return defaultValue;
            }
            else{
                return {"required":ERR_DATA["required"]};
            }
        }
        let dtDate = string.stringToDate(strValue,sShowDateTimeFormat);
        if(dtDate === false){
            return {"date":ERR_DATA["date"]};
        }
        return dtDate;//sau này mở rộng kiểm tra với constraints
    }
    /*-------------------------------------------------------------------------------------------------*/
    validate(){
        if(this.isValidated === false){
            this.value = datePicker.getDatePickerValue(this.inputDest.value.trim(),this.sShowDateTimeFormat,this.defaultValue,this.constraints);
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
            this.value = datePicker.getDatePickerValue(this.inputDest.value.trim(),this.sShowDateTimeFormat,this.defaultValue,this.constraints);
            this.isValidated = true;
            //console.log("datepicker getValue nocache");
        }
        return this.value;    
    }
    /*-------------------------------------------------------------------------------------------------*/
    initEvent(){
        //this.inputDest.addEventListener("input",this.input);
        this.inputDest.addEventListener("mouseup",this.mouseUp);
        this.inputDest.addEventListener("select",this.select);
        this.inputDest.addEventListener("cut",this.cut);
        this.inputDest.addEventListener("paste",this.paste);
  
        this.divDatePicker.addEventListener("focusin",this.focusIn);
        this.divDatePicker.addEventListener("focusout",this.focusOut);
        this.divDatePicker.addEventListener("keydown",this.keyDown);
        this.divDatePicker.addEventListener("click",this.click);
        this.divDatePicker.addEventListener("change",this.change);
        this.divDatePicker.addEventListener("mouseleave",this.mouseLeave);
    }
    /*-------------------------------------------------------------------------------------------------*/
    handleDateTimeChange(sOldValue){
        if(this.inputDest.value !== sOldValue){
            this.isValidated    = false;//có sự thay đổi value cần validate lại
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    triggerInputCommit(eventname){
        if (typeof this.onInputCommit !== 'function' && typeof this.onInputCommitError !== 'function') {
            return;
        }
        let dtValue =  this.getValue();
        let isChanged = !common.deepEqual(dtValue,this.prevValue); 
        if(ERR_DATA.isErrData(dtValue)){
            if (typeof this.onInputCommitError === 'function') {
                this.onInputCommitError(this.inputDest, dtValue, isChanged, eventname);
                this.prevValue = dtValue; //ghi lại giá trị trước đó, dù là có lỗi hay không
            }
            return;
        }
        /*Sở dĩ vẫn kích hoạt onInputCommit ngay cả khi isChanged === false vì
        tổng quát sau này có khả năng ngay cả khi isChanged = false vẫn phải làm
        một tác vụ gì đó, ví dụ như đóng calendar xổ xuống */
        if (typeof this.onInputCommit === 'function'){                    
            this.onInputCommit(this.inputDest,dtValue,isChanged,eventname);
            this.prevValue = dtValue;//gọi function xong set lại this.prevValue ;
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho inputDest*/
    mouseUp = ()=>{
        let caretPos = this.inputDest.selectionStart; // Lấy vị trí con trỏ
        let idx = this.getTokenIdxDateFormat(caretPos);
        if(idx === null){
            return;
        }
        if(this.dateTimeFormatTokens[idx]["type"] === "separator"){
            idx = idx +1;//chuyển sang token kế tiếp (thường thì là datetime)
            if(idx >= this.dateTimeFormatTokens.length){
                return;
            }
        }
        if(this.dateTimeFormatTokens[idx]["type"] === "datetime"){
            this.selectTextByTokenIdx(idx); 
        }    
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho inputDest*/
    select = (event) =>{
        let posStart = this.inputDest.selectionStart;
        let posEnd   = this.inputDest.selectionEnd;
        if(posStart === 0 && posEnd === this.inputDest.value.length){
            return;//đây là trường hợp select toàn bộ (ví dụ ấn CTRL+A)
        }
        let idx = this.getTokenIdxDateFormat(posStart);
        if(idx === null){
            return;
        }
        if(this.dateTimeFormatTokens[idx]["type"] ==="separator"){
            idx = idx +1;//chuyển sang token kế tiếp (thường thì là datetime)
            if(idx >= this.dateTimeFormatTokens.length){
                return;
            }
        }
        if(this.dateTimeFormatTokens[idx]["type"] === "datetime"){
            this.selectTextByTokenIdx(idx); 
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho inputDest*/
    cut = (event) =>{
        let caretPos = this.inputDest.selectionStart;
        let idx = this.getTokenIdxDateFormat(caretPos);
        if(idx === null){
            return;
        }
        this.keyDownInputDestDelete(event,idx);
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho inputDest*/
    paste = (event) =>{
        let caretPos = this.inputDest.selectionStart;
        let idx = this.getTokenIdxDateFormat(caretPos);
        //thực tế this.dateTimeFormatTokens[idx]["type"] === "separator"
        //không bao giờ xảy ra vì đã bị event select chặn rồi
        if(idx === null || this.dateTimeFormatTokens[idx]["type"] === "separator"){
            return;
        }
        let sValue = this.inputDest.value;
        let sPasteText = event.clipboardData.getData('text');
        let posStart = this.inputDest.selectionStart;
        let posEnd   = this.inputDest.selectionEnd;
        if(posStart ===0 && posEnd === this.inputDest.value.length){
            sPasteText = sPasteText.substring(0,this.inputDest.value.length);
            let dtDateTime = string.stringToDate(sPasteText,this.sShowDateTimeFormat);
            if(dtDateTime === false){//không đúng format
                event.preventDefault(); // Hủy hành động dán dữ liệu nếu dàn vào không là số
                return;
            }    
            this.inputDest.value = sPasteText;
        }
        else{
            let token = this.dateTimeFormatTokens[idx];
            let iLen = token["token"].length;
            sPasteText = sPasteText.substring(0,iLen);
            let fVal = parseInt(sPasteText, 10);
            if (isNaN(fVal)) {
                event.preventDefault(); // Hủy hành động dán dữ liệu nếu dàn vào không là số
                return;
            }
            let sReplace = fVal.toString().padStart(iLen,'0');
            let sValue =  this.inputDest.value;
            this.inputDest.value = 
            sValue.substring(0,token["pos"]) + sReplace + sValue.substring(token["pos"] + token["length"]);
        }
        this.handleDateTimeChange(sValue);
        event.preventDefault();
    }
    /*-------------------------------------------------------------------------------------------------*/
     /*event handler cho divDatePicker*/
    focusIn = (event)=>{
        if(event.target === this.inputDest){
            this.selectTextByTokenIdx(0);
        }
        else if(event.target.className === "btnDate"){
            let btnCsdrDate = this.divCalendar.querySelector("input.btnDate[considering]");
            if(btnCsdrDate){
                btnCsdrDate.removeAttribute("considering");
            }
            event.target.setAttribute("considering",true);
        }
        
    }    
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho divDatePicker*/
    focusOut = (event)=>{
        if(event.relatedTarget === null){
            event.target.focus();
            return;
        }
        if (this.divDatePicker.contains(event.relatedTarget)) {
            return;
        }
        this.divCalendar.style.display = "none";
        this.triggerInputCommit("datepickerout");
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho divDatePicker*/
    keyDown = (event)=>{
        let keyCode = event.key;
        if(keyCode === "Escape"){
            if(this.divCalendar){
                this.divCalendar.style.display = "none";
                this.inputDest.focus();
            }
        }
        else if(event.target === this.inputDest){
            this.keyDownInputDest(event);
        }
        /*else if(event.target === this.inputArrow){
            this.keyDownInputArrow(event);
        }*/
        else if(event.target.className === "btnDate"){
            this.keyDownBtnDate(event);
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho divDatePicker*/
    click = (event)=>{
        if(event.target === this.inputArrow){
            this.showHideCalendar();
        }
        else if(event.target.className === "btnDate"){
            this.selectDate(event.target);
        }
        else if(event.target.name === "prev-month" || event.target.name === "next-month"){
            this.prevNextMonth(event);
        }
        else if(event.target.name === "clear"){
            this.clearInputDest();
        }
        else if(event.target.name === "today"){
            this.today();
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho divDatePicker*/
    change = (event)=>{
        /*if(event.target.tagName !== "INPUT" || (event.target.name !== "month" && event.target.name !== "year") ){
            return;
        }*/
        if(event.target.tagName !== "INPUT"){
            return;
        }                    
        /*if(event.target === this.inputDest){
            this.changeInputDest();
        }   
        else */
        if(event.target.name === "month" || event.target.name === "year"){
            this.changeMonthOrYear(event);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler cho divDatePicker*/
    mouseLeave = (event)=>{
        event =event?event:window.event;
        let datePkObj = this; //phải dùng datePkObj vì trong setTimeout thí this sẽ thay đổi
        setTimeout(function(){ 
            let divCalendar = datePkObj.divCalendar;
            if(divCalendar){
                divCalendar.style.display='none';
            } 
        },10);
    };
    /*-------------------------------------------------------------------------------------------------*/
    // sử dụng objSmallCmb thay cho this vì trong một số hàm xử lý event của DOM element this được hiểu là bản thân element đó
    constructor(inputDest,defaultValue=null,options={}){
        //let datePicker = this;
        this.inputDest  = inputDest;
        this.datePickerClassName = options.hasOwnProperty("datePickerClassName") ? options.comboClassName : 'date-picker';//sau này dùng cho clearCombo
        this.onInputCommit = typeof options.onInputCommit === 'function' ? options.onInputCommit : null;
        this.onInputCommitError     = typeof options.onInputCommitError === 'function' ? options.onInputCommitError : null;
        this.sShowDateTimeFormat = options.hasOwnProperty("sShowDateTimeFormat") ? options.sShowDateTimeFormat : "dd/mm/yyyy";
        this.sShowDateTimeFormat = this.sShowDateTimeFormat.trim().toLowerCase(); 
        this.parseDateTimeFormat();
        this.sStartDate = "";
        this.sEndDate = "";
        
        this.defaultValue = defaultValue;//2024-07-14
        this.constraints = options.hasOwnProperty("constraints") ? options.constraints : null;
        inputDest.setAttribute("maxlength",this.sShowDateTimeFormat.length);
        //bufferNumber : cấu trúc lưu trữ tạm thời các ký tự số gõ vào dùng để cho cơ chế gõ số tiện dụng
        //vào date_picker
        this.bufferNumber = "";
        //this.tokenIdx để xét xem có sự dịch chuyển sang token khác không. Nếu có sự dịch chuyển token
        //thì xóa sạch buffer đi
        this.tokenIdx = -1;
        this.isValidated  = false; // chưa validate dữ liệu
        /*vì datePicker sử dụng event.preventDefault trong các sự kiện như keydown, cut, paste nên
        không xảy ra sự kiện input và change nữa do đó phải dùng biến this.isChanged để theo dõi sự kiện
        sự thay đỏi giá trị của inputDest*/
        this.value = null;
        if(this.onInputCommit || this.onInputCommitError){
            this.value = this.getValue();
            /* lưu giữ bản sao giá trị this.value để sau này so sánh khi commit*/
            this.prevValue = this.value; 
        }
        /*Begin: Tạo một thẻ DIV to bao quanh phía ngoài*/	
        let divDatePicker = document.createElement('DIV');
        let parent = inputDest.parentNode;
        parent.insertBefore(divDatePicker,inputDest);/*Chèn thẻ DIV vào trong container chứa thẻ INPUT inputDest*/
        divDatePicker.className = this.datePickerClassName;
        if(options.hasOwnProperty("zIndex")){
            divDatePicker.style.zIndex = options.zIndex;//2020-07-13
        }
        divDatePicker.appendChild(inputDest);/*Nhúng thẻ INPUT inputDest vào trong thẻ DIV*/
        /*End: Tạo một thẻ DIV to bao quanh phía ngoài*/
        if(this.inputDest.value === ""){
            this.inputDest.value = this.sShowDateTimeFormat;
        }
        if(this.sShowDateFormat === "" || !this.sShowDateFormat.includes("yyyy")){
            this.inputDest.style.flex = "1 1 1";
            this.inputArrow = null;
            this.divCalendar = null;
            this.divDatePicker = divDatePicker;
            this.initEvent();
            return;
        }     
          /*Begin: Tạo một thẻ INPUT chứa ảnh mũi tên */
        let inputArrow = document.createElement('INPUT');
        inputArrow.type      = 'button';
        this.inputArrow = inputArrow; 
        inputArrow.style.flex = "0 0 calc(1.0*"+divDatePicker.clientHeight+"px)";//mũi tên có kích thước rộng,cao bằng chiều cao date_picker
        divDatePicker.appendChild(inputArrow);
        /*End: Tạo một thẻ INPUT chứa ảnh mũi tên*/
        /*Begin: Tạo một thẻ DIV thứ 2 chứa các items */
        let divCalendar = document.createElement('DIV');
        this.divCalendar = divCalendar; 
        this.divCalendar.style.width = options.hasOwnProperty("boardCalendarWidth") ? options.boardCalendarWidth : "0px";/*đặt 0px thì giá trị min-width sẽ quyết định độ rộng thực tế của divCalendar*/
        divDatePicker.appendChild(divCalendar);
        this.divDatePicker = divDatePicker; 
        divCalendar.style.top = divDatePicker.clientHeight+"px";
        /*Nếu không đặt độ cao của một ô trong calendar thì đặt ngầm định bằng độ rộng của ô đó = 13% độ rộng của divCalendar*/
        this.itemDateHeight = options.hasOwnProperty("itemDateHeight") ? options.itemDateHeight : 0;
        /*End: Tạo một thẻ DIV thứ 2 chứa các items */
        //this.currentDate =  string.stringToDate(inputDest.value,this.sShowDateTimeFormat);
     
        /*Begin đặt event handler cho các phần tử. Chú ý phần này bao giờ cũng phải đặt xuống
         * dưới cùng khi các properties của Object đã được thiết lập ổn định thì event handler chạy
         * mới chính xác. Nếu đặt event handler ở giữa khởi tạo các thành phần của object thì có
         * thể một số event ví dụ blur, focus ... sẽ chạy trước cả khi khởi tạo xong object =>
         * sẽ không chính xác*/
    
        this.initEvent();
    }
}
/*-------------------------------------------------------------------------------------------------*/
