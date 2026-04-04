const USER_RIGHT = {
    "no_acess_right"    : 0b0000000000000000,
    "help_read_right"   : 0b0000000000000001,
    "read_right"        : 0b0000000000000010,
    "update_right"      : 0b0000000000000100,
    "delete_right"      : 0b0000000000001000,
    "add_right"         : 0b0000000000010000,
    "full_right"        : 0b1111111111111111
};
const SYSTEM_STATUS = {
    "normal":            "normal",
    "suspended":         "suspended",
    "unauthenticated":   "unauthenticated",
    "access_denied":     "access_denied"
}
const EXT_ARRAY = {
    "branch":   1,
    "leaf":     2
}
const LIST_SEPARATOR_CHAR   = "+";//ký tự + đã mã h phân cách trong 1 string biểu diễn danh sách nhiều phần tử. Ví dụ "element1+element2+element3"
const MIN_DATE = "1000-01-01";// trùng với khai báo trong sysenv
/*----------------------------------------------------------------------------------------------------*/
const common = {
    //Tạo 1 global Object có ý nghĩa như là 1 namespace       
};
/*-------------------------------------------------------------------------------------------------*/
common.screenResolution = function(){
    let w_screen = screen.width;
    if(w_screen<=1024){
        w_screen = 1024;
    }
    document.getElementById("outer").style.width = String(w_screen -22) + "px"; 
}
/*-------------------------------------------------------------------------------------------------*/
common.getRelativeURL = function(){
    return window.location.pathname + window.location.search;
}
/*-------------------------------------------------------------------------------------------------*/
common.getRelativeURLNotFileName = function(){
    let sPathName = window.location.pathname;
    return sPathName.substring(0, sPathName.lastIndexOf('/')) + "/";
}
/*-------------------------------------------------------------------------------------------------*/
/*Hiện vẫn dùng được cú pháp url = new URL(sUrl,base). ví lý do sURl có thể là relative URL hoặc
 * absolute URL nên không trả về Url chính xác được. Nếu dùng new URL(sUrl,base) thì đều phải trả lại
 * dạng absolute URL
  */
common.setURLParam = function(sUrl,sParam,sValue){
    let url=null;
    let isRelativeUrl;
    try{
        url = new URL(sUrl);//thử xem có phải lả url tuyệt đối không
        isRelativeUrl = false;
    }
    catch(err){
        try{
            url = new URL(sUrl,window.location.href);//địa chỉ tương đối
            isRelativeUrl = true;
        }
        catch(err_1){
            return null; //url không hợp lệ
        }
    }
   // if(sValue ===""){
    if(sValue === null){
        url.searchParams.delete(sParam);
    }
    else{
        url.searchParams.set(sParam,sValue);
    }
    if(isRelativeUrl){
        return url.pathname + url.search;
    }
    else{
        return url.href;
    }
    /*var regx = new RegExp(sParam+'=[^&]+',"g");
    if(sUrl.match(regx)){
        sUrl =sUrl.replace(regx, sParam+"="+sValue);
    }
    else if(sUrl.match(/\?/)){
        sUrl = sUrl+"&"+sParam+"="+sValue;
    }
    else{
        sUrl = sUrl+"?"+sParam+"="+sValue;
    }
    return sUrl;*/
    
}
/*----------------------------------------------------------------------------------------------------*/
/*https://stackoverflow.com/questions/979975/get-the-values-from-the-get-parameters-javascript
 * run with Chrome from version 51 ( year 2016) 
 */
common.getURLParam = function(sUrl,sParam){
    //nếu sUrl là 1 địa chỉ tuyệt đối thì window.location.href sẽ bị bỏ qua
    let url = new URL(sUrl,window.location.href);
    return url.searchParams.get(sParam);//return null if not sParam
}
/*----------------------------------------------------------------------------------------------------*/
/*Nguồn https://stackoverflow.com/questions/5796718/html-entity-decode 
 * http://benalman.com/news/2010/11/immediately-invoked-function-expression/
 Function này đã được viết lại sử dụng kỹ thuật namespace chứ không dùng IIFE
 */

common.divToDecode = document.createElement("DIV");//chỉ tạo 1 lần duy nhất
common.decodeHTMLEntities =  function(str) {
    if(str && typeof str === 'string') {
      // strip script/html tags
      str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
      str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
      common.divToDecode.innerHTML = str;
      str = common.divToDecode.textContent;
      common.divToDecode.textContent = '';
    }
    return str;
}
/*----------------------------------------------------------------------------------------------------*/
/*
 * 
 * @param {htmlObject} htmlObj: đỗi tượng HTML thể hiện lỗi
 * jsonData: dữ liệu kiểm json
 * successFunct: callBack function được gọi khi jsonData không có lỗi
 * failFunct: callBack function được gọi khi jsonData có lỗi
 * isThrowError: chỉ có ý nghĩa khi failFunct = null. Sau khi hệ thống xử lý lỗi xong sẽ throw Error đẻ ngắt
 * @Description
 * Nếu jsonData có lỗi và có hàm failFunct thì failFunct được gọi
 * Nếu jsonData không lỗi và có hàm successFunct thì successFunct được gọi
 * Nếu không phải 2 tình huống trên thì hàm số tự xử lý và show dữ liệu jsonData lên htmlObj
 * Nếu isThrowError = true và có lỗi thì sau khi xử lý xong hệ thống sẽ throw ra error. 
 * Update 2022-12-21
 */
common.showUIAndControlError = function(htmlObj,jsonData,successFunct=null,failFunct=null,isThrowError=false){
    let isError ;
    /*update 2022-12-21*/
    /*if( jsonData === undefined || //cẩn thận
        !jsonData.hasOwnProperty("status") || //tình huống này khi  jsonData gồm nhiều phần [part1, part2,part3], có thể có lỗi ở cục bộ các phần nhưng tổng thể không có lỗi
        (jsonData["status"] !==ERR_STATUS.server_error && jsonData["status"] !==ERR_STATUS.server_logic_error)
    ){
        isError = false;
    }
    else{
        isError = true; // mean jsonData["status"] ===ERR_STATUS.server_error || jsonData["status"] === ERR_STATUS.server_logic_error
    }*/
    let jsonDataExist=true;
    if(!jsonData ||  //tình huống này xảy ra khi lỗi không có được dữ liệu
        (jsonData.hasOwnProperty("status") && (jsonData["status"] ===ERR_STATUS.server_error||jsonData["status"] ===ERR_STATUS.server_logic_error))){
        isError = true;
        if(!jsonData){
            jsonDataExist = false;
        }
    }
    else{
        isError = false;
    }
    if(isError){
        if(jsonDataExist){//các lỗi suspended,unauthenticated,access_denied thì xử lý redirect rồi thoái
            if(jsonData["info"] === SYSTEM_STATUS.suspended){
                window.system_status = jsonData["info"]; //bặt flag này để handler confirmBeforeUnloadWindow của event beforeunload của window sẽ không chặn sự kiện nữa
                window.location.href = "/suspend.php";
                return;
            }
            else if(jsonData["info"] === SYSTEM_STATUS.unauthenticated){
                window.system_status = jsonData["info"]; //bặt flag này để handler confirmBeforeUnloadWindow của event beforeunload của window sẽ không chặn sự kiện nữa
                window.location.href = "login.php?redirectto="+encodeURIComponent(common.getRelativeURL());
                return;
            }
            else if(jsonData["info"] === SYSTEM_STATUS.access_denied){
                window.system_status = jsonData["info"]; //bặt flag này để handler confirmBeforeUnloadWindow của event beforeunload của window sẽ không chặn sự kiện nữa
                window.location.href = "/error.php?err="+SYSTEM_STATUS.access_denied+"&pathHP="+encodeURIComponent(common.getRelativeURLNotFileName());
                return;
            }
        }
        
        if(failFunct){
            failFunct();
            if(isThrowError){
                console.log("Chủ động throw ra lỗi (phân biệt với lỗi ngoài kiểm soát)") ;
                throw Error (jsonData["extra"]);
            }
            return;
        } 
    }
    else if(successFunct){
        successFunct();
        return;
    }
    //from here mean (isError && !failFunct) || (!isError && !successFunct)
    ///tức là tự xử lý khi không sử dụng được successFunct và failFunct
    let value;
    if(isError){//có lỗi
        if(jsonDataExist){
            value = jsonData["extra"];
        }
        else{
            value = "Function common.showUIAndControlError error: jsonData is undefined";
        }
    }
    else{
        value = jsonData["info"];//không lỗi
    }
    if(htmlObj.tagName === "INPUT" || htmlObj.tagName === "TEXTAREA"){
        htmlObj.value = value;
    }
    else if(isError && htmlObj.tagName === "TABLE"){
         htmlObj.innerHTML = "<tbody><tr><td>"+value+"</td></tr></tbody>";
    }
    else{
        htmlObj.innerHTML = value;
    }
    if(isError){//2022-03-13, xử lý nếu lỗi
        htmlObj.style.display = "block";
    }
    
    if(isError && isThrowError){
        throw Error (jsonData["extra"]);
    }
    
};
/*----------------------------------------------------------------------------------------------------*/



common.loadingStatus = function(container, isLoading, options = {}) {
  // Default options
  const default_opts = {
    text: "",             // nội dung text
    spinner: "circle",    // "circle" | "bar"
    textPosition: "center", // "center" | "bottom" | "none"
    size: 4.8,
    blockUI: true         // true = chặn event, false = pointer-events: none
  };
  const opts = Object.assign(default_opts, options);
  opts.size = Math.max(opts.size, 1.6);
  // Kiểm tra div.loading
  let loading = container.querySelector(".loading");
  if (isLoading) {
    if (!loading) {
      loading = document.createElement("div");
      loading.className = `loading loading--${opts.spinner} loading--text-${opts.textPosition}`;
       loading.style.setProperty("--spinner-size", `${opts.size}rem`);
      // Spinner
      const spinner = document.createElement("div");
      spinner.className = "loading__spinner";
      loading.appendChild(spinner);
      
      // Text
      if (opts.textPosition !== "none" && opts.text) {
        const textEl = document.createElement("div");
        textEl.className = "loading__text";
        textEl.innerText = opts.text;
        loading.appendChild(textEl);
      }

      container.appendChild(loading);
    } else {
      // Update text
      const textEl = loading.querySelector(".loading__text");
      if (textEl) {
        textEl.innerText = opts.text;
      }
      // Update modifiers
      loading.className = `loading loading--${opts.spinner} loading--text-${opts.textPosition}`;
      loading.style.display = "flex";
      
    }

    // Pointer-events
    loading.style.pointerEvents = opts.blockUI ? "all" : "none";
    
  } else {
    if (loading) {
      loading.style.display = "none";
    }
  }
};



/*------------------------------------------------------------------------------------------------------------------------------------*/
/*reference https://stackoverflow.com/questions/679915/how-do-i-test-for-an-empty-javascript-object*/
common.isEmpty = function(obj) {//check object is empty
    if(obj ===null || obj === undefined){
        return true; //2022-03-02
    }
    for(let key in obj) {
        if(obj.hasOwnProperty(key) && typeof obj[key]!=="function")
            return false;
    }
    if(Array.isArray(obj)){ //bổ sung 2024-02-18 để dùng cho cả trường hợp obj là array
        return obj.length === 0;
    }
    return JSON.stringify(obj) === JSON.stringify({});
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/* reference https://stackoverflow.com/questions/175739/built-in-way-in-javascript-to-check-if-a-string-is-a-valid-number */
common.isNumeric = function(str) {
   // if (typeof str !== "string") return false; // we only process strings!  
    str = String(str).replace(/,/g,"");
    if( isNaN(str) || // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
           isNaN(parseFloat(str))) return false; // ...and ensure strings of whitespace fail
    return parseFloat(str);       
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*reference
 https://stackoverflow.com/questions/175739/built-in-way-in-javascript-to-check-if-a-string-is-a-valid-number
 */
common.isInteger = function(str) {
    //if (typeof str !== "string") return false; // we only process strings!  
    str = String(str).replace(/,/g,"");
    if( isNaN(str) || // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
        isNaN(parseFloat(str))||
        !Number.isInteger(parseFloat(str))) return false;
    return parseInt(str);    
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
//https://stackoverflow.com/questions/11832914/how-to-round-to-at-most-2-decimal-places-if-necessary
//Các hàm như rouund hay toFixed của javaScript không chính xác
common.roundNumber = function(fNum,iPrecision){
    //fNum = parseFloat(fNum.replace(/,/g,""));
    fNum = common.isNumeric(fNum);
    if(fNum===false){
        return false;
    }
    let pow10 = Math.pow(10,iPrecision); 
    return Math.round((fNum + Number.EPSILON) * pow10) / pow10;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*
 https://stackoverflow.com/questions/2901102/how-to-print-a-number-with-commas-as-thousands-separators-in-javascript
 * @param {number} fNumber
 * @returns {string}
 */
common.numberWithCommas = function(fNumber) {
    //Seperates the components of the number
    let n= fNumber.toString().split(".");
    //Comma-fies the first part
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    //Combines the two sections
    return n.join(".");
}
/*----------------------------------------------------------------------------------------------------------------------------*/
/*Replace nội dung cũ giữa 2 thẻ comment "<!--begin sMarkup -->old content<!--end sMarkup-->"
 * trở thành "<!--begin sMarkup -->new content<!--end sMarkup-->"
 * Hàm số ứng dụng trong thay thế việc dùng: divX.innerHTML = "something" trong tình huống không có thẻ bao ngoài
 */
common.replaceTextMarkup = function(sText,sReplace,sMarkup){
    let reg = new RegExp("<!--begin "+sMarkup+"-->.*?<!--end "+sMarkup+"-->","g");
    sText = sText.replace(reg,"<!--begin "+sMarkup+"-->"+sReplace+"<!--end "+sMarkup+"-->");
    return sText;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*
 * Số ngày trong tháng
 * @param {Date} dtDate
 * @returns 
 */
common.getNumDaysOfMonth = function(dtDate){
    if(typeof(dtDate.getMonth)!=="function"){
        return false;
    }
    return new Date(dtDate.getFullYear(),dtDate.getMonth()+1,0).getDate();
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.previousMonth = function(dtDate){
    if(typeof(dtDate.getMonth)!=="function"){
        return false;
    }
    let dtPrev;
    let iCurrentDay = dtDate.getDate();
    if(dtDate.getMonth()>0){
        dtPrev = new Date(dtDate.getFullYear(),dtDate.getMonth()-1);//ngày mặc định là 01
    }
    else{
        dtPrev = new Date(dtDate.getFullYear()-1,11);//ngày mặc định là 01
    }
    //Số ngày trong tháng
    let iNumDays = new Date(dtPrev.getFullYear(),dtPrev.getMonth()+1,0).getDate();
    if(iCurrentDay>iNumDays){
        iCurrentDay = iNumDays;
    }
    dtPrev.setDate(iCurrentDay);
    return dtPrev;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.nextMonth = function(dtDate){
    if(typeof(dtDate.getMonth)!=="function"){
        return false;
    }
    let dtNext;
    let iCurrentDay = dtDate.getDate();
    if(dtDate.getMonth()===11){
        dtNext = new Date(dtDate.getFullYear()+1,0);//ngày mặc định là 01
    }
    else{
        dtNext = new Date(dtDate.getFullYear(),dtDate.getMonth()+1);//ngày mặc định là 01
    }
    //Số ngày trong tháng
    let iNumDays = new Date(dtNext.getFullYear(),dtNext.getMonth()+1,0).getDate();
    if(iCurrentDay>iNumDays){
        iCurrentDay = iNumDays;
    }
    dtNext.setDate(iCurrentDay);
    return dtNext;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.getFirstDayOfMonth = function(dtDate) {
    return new Date(dtDate.getFullYear(),dtDate.getMonth());
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.getLastDayOfMonth = function(dtDate) {
    return new Date(dtDate.getFullYear(), dtDate.getMonth() + 1, 0);
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.dayAfterOffset = function(dtDate,iOffsetDay){
    if(typeof(dtDate.getMonth)!=="function"){
        return false;
    }
    return new Date(dtDate.getTime() + 24*3600000*iOffsetDay);
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.createArray = function(length) {
    let arr = new Array(length || 0);
    let i = length;
    if (arguments.length > 1) {
        let args = Array.prototype.slice.call(arguments, 1);
        while(i--) arr[length-1 - i] = common.createArray.apply(this, args);
    }
    return arr;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.coloringTable = function(arrField,nRow){
    let nCol = arrField.length;
    let painting = common.createArray(nRow,nCol);//cấu trúc ma trận thưa là để tạo bảng sơn màu cho việc tạo bảng action
    let action = common.createArray(nRow,nCol);//cấu trúc ma trận thưa ghi lại action
    for(let r =0; r < nRow; r++){
        let oldColor    = null;
        let countBlue   = 0;
        let idxBlue     = 0;     
        for(let c =0; c < nCol; c++){
            if(painting[r][c]){//đã painting trước đó
                continue;
            }
            let iHeight = arrField[c].hasOwnProperty("rowspan")? arrField[c]["rowspan"]*1.0:1; 
            let color =  iHeight >= (nRow-r) ? "red": "blue";//nRow-r là khoảng cách từ block đó đến "đáy". iHeight là độ cao của block
            if(color === "red"){//tô màu đỏ 1 cột thẳng đứng
                for(let idx = r;idx <(iHeight+r);idx++){
                    painting[idx][c] = "red";
                }
                action[r][c] = {"color":"red","length":iHeight};
            }
            else if(color === "blue"){
                painting[r][c] = "blue";
                if(oldColor !== color){
                    countBlue = 0;
                    idxBlue = c;
                }
                countBlue++;
            }
            if( (color === "red" || c === nCol-1)  // hiện tại là màu đỏ hoặc đã đi tới ô cuối
                && oldColor === "blue"){// trước đó tô màu xanh
                action[r][idxBlue] = {"color":"blue","length":countBlue};
            }
            oldColor = color;    
        }
    }
    return action;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/* reference https://stackoverflow.com/questions/1527803/generating-random-whole-numbers-in-javascript-in-a-specific-range
 * Returns a random integer between min (inclusive) and max (inclusive).
 * The value is no lower than min (or the next integer greater than min
 * if min isn't an integer) and no greater than max (or the next integer
 * lower than max if max isn't an integer).
 * Using Math.round() will give you a non-uniform distribution!
 */
common.getRandomInt= function(min, max) {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min + 1)) + min;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*
 * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Set
 * THực hiện phép trừ tập hợp A-B. 
 */
common.diffSet = function(setA, setB){
    let diff = new Set(setA)
    for (let elem of setB) {
        diff.delete(elem)
    }
    return diff;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.unionSet = function(setA, setB) {
    let union = new Set(setA)
    for (let elem of setB) {
        union.add(elem)
    }
    return union
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*INPUTS
 * objData là  1 arrData có dạng [0=>element_0, 1=> element_1,....n=>element_n]
 * sData    : tên cột chứa data
 * sTitle   : tên cột chứa title
 * Trong đó element_x bất kỳ có dạng  ["key1"=> value1 ,"key2"=> value2,....]
 * formatFullFormatToArrayULLI sẽ trả về định dạng dữ liệu dạng array array [0=>element_0, 1=> element_1,....n=>element_n]
 * trong đó element_x bất kỳ có dạng ["data"=>data_value,"title"=>"title_value","sub"=>""]
 * Format này dùng để hiện thị trong các menu dữ liệu dạng UL-UI
 */
common.formatFullFormatToArrayULLI = function(arrData,sData,sTitle){
    let arrDataFormat =  [];
    for (let i = 0; i < arrData.length; i++){
        let obj = {};
        for(let prop in arrData[i]){
            if(prop === sData){
                obj.data = arrData[i][prop];
            }
            if(prop === sTitle){
                obj.title = arrData[i][prop];
            } 
        }
        obj["sub"] = "";
        arrDataFormat[i] = obj;    
    }
    return arrDataFormat;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*INPUTS
 * jsonData là 1 arrData có dạng {"key1"=> value1 ,"key2"=> value2,....}
 * formatKeyPairToArrayULLI sẽ trả về định dạng dữ liệu dạng array [0=>element_0, 1=> element_1,....n=>element_n]
 * trong đó element_x bất kỳ có dạng ["data"=>data_value,"title"=>"title_value","sub"=>""]
 * Format này dùng để hiện thị trong các menu dữ liệu dạng UL-UI
 */
common.formatKeyPairToArrayULLI = function(jsonData){
    let arrDataFormat =  [];
    for(let sKey in jsonData){
         let obj = {};
         obj.data = sKey;
         obj.title = jsonData[sKey];
         obj["sub"] = "";
         arrDataFormat.push(obj);
    }
    return arrDataFormat;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/

/*Trong HTML body có thẻ main chứa nội dung chính của page. Với mỗi controller-view khác nhau cần set cho MAIN tag
 *một data code khác nhau để còn set các css khác nhau lên main 
 * 
 * @param {string} sDataCode
 */
common.setDataCodeForMainTag = function(sDataCode){
    let htmlMain = document.getElementsByTagName("MAIN")[0];
    htmlMain.setAttribute("data-code",sDataCode);
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Thuật toán createDictTree này nhằm phục vụ cho việc sau này tìm kiếm một từ bất kỳ trong một câu, hoặc một cụm từ chứa các từ 
 * ngăn cách nhau bới dấu blank. Ví dụ jsonData = {"1": "phí trọng tuấn","2":"phí bảo trâm"} thì
 * nó sẽ add 6 từ sau vào trong tree: "phí trọng tuấn", "trọng tuấn","tuấn", "phí bảo trâm","bảo trâm","trâm"
 * như thế sau này khi search trên key thì dù bạn có gõ "phí", hay "bảo", hay "trâm" thì nó vẫn tìm ra được "phí bảo trâm"
 * 
 * @param {type} jsonData: Cấu trúc có dạng {key1: value1, key2:value2,.....}
 * @param {type} sOptionAddExistWord: có thể là "replace_key", "array_key" hoặc "". Tuỳ chọn này áp dụng khi add 1 word đã có vào tree
 * @returns : trả về dữ diệu dạng dictTree
 */
common.createDictTree = function(jsonData,sOptionAddExistWord){
    let dictTreeData = new dictTree();
    dictTreeData.optAddExistWord = sOptionAddExistWord; 
    for(let sKey in jsonData){
        let sVal = String(jsonData[sKey]);
        let arrWord = sVal.split(/\s+/);
        let iNum = arrWord.length;
        for(let i=0;i<iNum;i++){
            let sWord = arrWord.join(" "); // qui chuẩn lại dấu blank ở word
            dictTreeData.addWord(sWord,sKey);
            //console.log(sWord + "-" + sKey);
            arrWord.shift();
        }
    }
    return dictTreeData;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Reference https://stackoverflow.com/questions/3955229/remove-all-child-elements-of-a-dom-node-in-javascript
 * :Lựu chọn cho hiệu suất cao hơn viết node.innerHTML = ""
 * @param {type} node
 * Hiện giải pháp container.replaceChildren(...arrayOfNewChildren) còn khá mới 2020 nên chưa dùng được
 * @returns {undefined}
 */
common.removeAllChild = function(node){
    while (node.firstChild) {
        node.removeChild(node.lastChild);
    }
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*reference https://stackoverflow.com/questions/20514596/document-documentelement-scrolltop-return-value-differs-in-chrome/33462363#33462363 */
common.getScroolTop = function(){
    return window.pageYOffset || document.body.scrollTop;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*reference https://stackoverflow.com/questions/20514596/document-documentelement-scrolltop-return-value-differs-in-chrome/33462363#33462363 */
common.getScroolLeft = function(){
    return window.pageXOffset || document.body.scrollLeft;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Tính ra tọa độ X-View Pos của htmlObj khi thanh H Scrool có tọa độ bằng 0*/
common.getXPosViewWhenZeroHScrool = function(htmlObj){
    let rect = htmlObj.getBoundingClientRect();
    let iScrollLeft = common.getScroolLeft();
    return rect.left + iScrollLeft; //phải cộng thêm iScrollLeft vì khi thanh H Scrool dịch chuyển thì rect.top sẽ sụt đi
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Tính ra tọa độ X-View Pos của htmlObj khi thanh H Scrool có tọa độ bằng 0*/
common.getYPosViewWhenZeroVScrool = function(htmlObj){
    let rect = htmlObj.getBoundingClientRect();
    let iScrollTop = common.getScroolTop();
    return rect.top + iScrollTop; //phải cộng thêm iScrollTop vì khi thanh V Scrool dịch chuyển thì rect.top sẽ sụt đi
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.fixedHTMLObjXPos = function(htmlObjs,iXPos){
    let iScrollLeft = common.getScroolLeft();
    if(iScrollLeft>iXPos){
        if(htmlObjs instanceof NodeList && htmlObjs.length>0){
            for(let i=0;i<htmlObjs.length;i++){
                htmlObjs[i].style.left = String(iScrollLeft - iXPos) +"px";
            }
        }
        else if(htmlObjs instanceof Object && htmlObjs !==null){
            htmlObjs.style.left = String(iScrollLeft - iXPos) +"px";
        }

    }else{
        if(htmlObjs instanceof NodeList && htmlObjs.length>0){
            for(let i=0;i<htmlObjs.length;i++){
                htmlObjs[i].style.left = "0px";
            }
        }
        else if(htmlObjs instanceof Object && htmlObjs !==null){
            htmlObjs.style.left = "0px";
        }
    }
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.fixedHTMLObjYPos = function(htmlObjs,iYPos){
    let iScrollTop = common.getScroolTop();
    if(iScrollTop>iYPos){
        if(htmlObjs instanceof NodeList && htmlObjs.length>0){
            for(let i=0;i<htmlObjs.length;i++){
                htmlObjs[i].style.top = String(iScrollTop - iYPos) +"px";
            }
        }
        else if(htmlObjs instanceof Object && htmlObjs !==null){
            htmlObjs.style.top = String(iScrollTop - iYPos) +"px";
        }

    }else{
        if(htmlObjs instanceof NodeList && htmlObjs.length>0){
            for(let i=0;i<htmlObjs.length;i++){
                htmlObjs[i].style.top = "0px";
            }
        }
        else if(htmlObjs instanceof Object && htmlObjs !==null){
            htmlObjs.style.top = "0px";
        }
    }
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Nguồn dẫn http://www.alexandre-gomes.com/?p=115
 * 
 * @returns {Number}
 */
common.getScrollBarWidth = function() {  
    var inner = document.createElement('p');  
    inner.style.width = "100%";  
    inner.style.height = "200px";  
  
    var outer = document.createElement('div');  
    outer.style.position = "absolute";  
    outer.style.top = "0px";  
    outer.style.left = "0px";  
    outer.style.visibility = "hidden";  
    outer.style.width = "200px";  
    outer.style.height = "150px";  
    outer.style.overflow = "hidden";  
    outer.appendChild (inner);  
  
    document.body.appendChild (outer);  
    var w1 = inner.offsetWidth;  
    outer.style.overflow = 'scroll';  
    var w2 = inner.offsetWidth;  
    if (w1 == w2) w2 = outer.clientWidth;  
  
    document.body.removeChild (outer);  
  
    return (w1 - w2);  
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*https://stackoverflow.com/questions/9585973/javascript-regular-expression-for-rgb-values
 * 
 * @param {string} sRGBFunction: có dạng rbg(rvalue, gvalue, bvalue)
 * @returns {array}
 */
common.getRGB = function(sRGBFunction) {
    let matchColors = /rgb\((\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\)/;
    let match = matchColors.exec(sRGBFunction);
    if (match !== null) {
        return [1.0*match[1], 1.0*match[2], 1.0*match[3]];
    }
    return null;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*https://www.geeksforgeeks.org/binary-search-in-javascript*/

common.binarySearchOnSortedArr = function(arr, x, iStart, iEnd){
    // Base Condition
    if (iStart >= iEnd) return iStart;
    // Find the middle index
    let iMid=Math.floor((iStart + iEnd)/2);
    // Compare mid with given key x
    if (arr[iMid]=== x) return iMid;
    // If element at mid is greater than x,
    // search in the left half of mid
    if(arr[iMid] > x){ 
        return common.binarySearchOnSortedArr(arr, x, iStart, iMid-1);
    }
    else{
        // If element at mid is smaller than x,
        // search in the right half of mid
        return common.binarySearchOnSortedArr(arr, x, iMid+1, iEnd);
    }    
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.getNextOrPrevControl=function(control,nodeListControl,isPrev){
    let idx = 0;
    for(idx=0;idx<nodeListControl.length;idx++){
        if(nodeListControl[idx] === control){
            break;
        }
    }
    if(nodeListControl[idx]!==control){
        return null;
    }
    if(isPrev && idx >0){
        return nodeListControl[idx-1];
    }
    if(!isPrev && idx < nodeListControl.length-1){
        return nodeListControl[idx+1];
    }
    return null;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.getKeyByValueInJSONData =function(value,jsonData){
    if(common.isEmpty(jsonData)){
        return null;
    }
    for(const sKey in jsonData){//hasOwnProperty để loại bỏ các thuộc tính kế thừa ??
        if (jsonData.hasOwnProperty(sKey) && value === jsonData[sKey]){
            return sKey; 
        }
    }
    return null;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
common.getKeyByValueInDictTree =function(value,dictTreeData){
    if(common.isEmpty(dictTreeData)){
        return null;
    }
    let objSearch = dictTreeData.search(value,1);
    let keySearch = objSearch !==null ? Object.keys(objSearch):null;
    if (keySearch !==null && keySearch.length === 1){//keySearch.length >1 nghĩa là quá nhiều giá trị thỏa mãn
        return keySearch[0];
    }
    return null;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*Hàm này cũng chỉ chính xác tương đối, khi x,y là các đối tượng quá phức tạp thì sẽ không đúng
 * lúc đó có thể dùng thư viện lodash hàm _.isEqual*/

/*------------------------------------------------------------------------------------------------------------------------------------*/
/*viết với sự trợ giúp của chatGPT. Chưa có bổ sung cơ chế chống đệ quy vô hạn khi gặp object tham chiếu vòng (circular reference) 
 * Có thể dùng thư viện lodash hàm _.isEqual*/
common.deepEqual = function (a, b) {
    if (a === b) {
        // Đặc biệt xử lý +0 và -0
        return a !== 0 || 1 / a === 1 / b;
    }

    // Xử lý NaN
    if (typeof a === 'number' && typeof b === 'number') {
        return isNaN(a) && isNaN(b);
    }

    // null hoặc undefined
    if (a == null || b == null) return false;

    // So sánh kiểu
    const typeA = Object.prototype.toString.call(a);
    const typeB = Object.prototype.toString.call(b);
    if (typeA !== typeB) return false;

    switch (typeA) {
        case '[object Date]':
            return a.getTime() === b.getTime();

        case '[object RegExp]':
        case '[object Function]':
            return a.toString() === b.toString();

        case '[object BigInt]':
        case '[object Number]':
        case '[object String]':
        case '[object Boolean]':
        case '[object Symbol]':
            return Object.is(a, b);

        case '[object Array]':
        case '[object Object]': {
            const keysA = Object.keys(a);
            const keysB = Object.keys(b);
            if (keysA.length !== keysB.length) return false;
            for (let key of keysA) {
                if (!keysB.includes(key) || !common.deepEqual(a[key], b[key])) {
                    return false;
                }
            }
            return true;
        }

        case '[object Set]': {
            if (a.size !== b.size) return false;
            const arrA = Array.from(a);
            const arrB = Array.from(b);
            return arrA.every(valA =>
                arrB.some(valB => common.deepEqual(valA, valB))
            );
        }

        case '[object Map]': {
            if (a.size !== b.size) return false;
            for (let [keyA, valA] of a) {
                let matched = false;
                for (let [keyB, valB] of b) {
                    if (common.deepEqual(keyA, keyB) && common.deepEqual(valA, valB)) {
                        matched = true;
                        break;
                    }
                }
                if (!matched) return false;
            }
            return true;
        }
        default:
            return false; // Không hỗ trợ WeakMap, WeakSet, v.v.
    }
};