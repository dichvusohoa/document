var string = {   
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
string.splitDateTimeFormatIntoParts = function(sDateTimeFormat){
    let re = {};
    re.sDateFormat = "";
    re.sTimeFormat = "";
    re.isDateFirst = null;
    //tìm vị trí có 1 hoặc nhiều ký tự \s kẹp giữa các ký tự a-zA-Z0-9 
    let match = sDateTimeFormat.match(/[a-zA-Z0-9]\s+[a-zA-Z0-9]/);
   
    if(match){
        let parts = [];
        parts[0] = sDateTimeFormat.slice(0,match.index+1);
        parts[1] = sDateTimeFormat.slice(match.index + match[0].length-1);
        for(let i=0;i<parts.length;i++){
            if(parts[i].includes("yyyy") || parts[i].includes("dd")){
                re.sDateFormat = parts[i];
            }
            else if(parts[i].includes("hh") || parts[i].includes("ss")){
                re.sTimeFormat = parts[i];
            }
        }
        if(parts[0].includes("yyyy") || parts[0].includes("dd")){
            re.isDateFirst  = true;
        }
        else{
            re.isDateFirst  = false;
        }
    }
    else if(sDateTimeFormat.includes("yyyy")|| sDateTimeFormat.includes("dd")){
        re.sDateFormat = sDateTimeFormat;
        re.isDateFirst  = true;
    }
    else if(sDateTimeFormat.includes("hh")|| sDateTimeFormat.includes("ss")){
        re.sTimeFormat = sDateTimeFormat;
        re.isDateFirst  = false;
    }
    return re;
}

/*
 * 
 * @param {type} dtDate
 * @param {type} sFormat
 * @returns Date value nếu thành công và false nếu thất bại. Nếu sDate = "" return false
 */
string.stringToDate = function(sDate, sFormat) {
    if (typeof(sDate) !== "string") {
        return false;
    }
    sFormat = sFormat.toLowerCase();
    if (sDate === "") {
        return false;
    }

    /* Convert format into a regular expression. Riêng với hh thì dùng replaceAll vì mm
    có thể là month hoặc minute tức là xuất hiện nhiều lần
    */
    let regex = sFormat.replace("yyyy", "(\\d{4})")
                       .replaceAll("mm", "(\\d{1,2})") 
                       .replace("dd", "(\\d{1,2})")
                       .replace("hh", "(\\d{1,2})")
                       .replace("ss", "(\\d{1,2})");

    let regDate = new RegExp("^" + regex + "$");
    let match = sDate.match(regDate);

    if (match === null) {
        return false;
    }
    //let iYear = 1970, iMonth = 0, iDay = 1, iHour = 0, iMinute = 0, iSecond = 0;
    let today = new Date();
    let iYear = today.getFullYear(), iMonth = today.getMonth(), iDay = today.getDate(); 
    let iHour = 0, iMinute = 0, iSecond = 0;
    let formatOrder = sFormat.split(/[^A-Za-z0-9]+/);
    let arrMeaningMM = [];
    let parts = string.splitDateTimeFormatIntoParts(sFormat);
    let matches = sFormat.match(/mm/g);//xem có bao nhiêu token mm
    let countMM  = matches ? matches.length : 0;
    if(countMM === 1){
        if(parts["isDateFirst"] === true){
            arrMeaningMM[0] = "month";
        }
        else if(parts["isDateFirst"] === false){
            arrMeaningMM[0] = "minute";
        }
    }
    else if(countMM === 2){
        if(parts["isDateFirst"] === true){
            arrMeaningMM[0] = "month";
            arrMeaningMM[1] = "minute";
        }
        else if(parts["isDateFirst"] === false){
            arrMeaningMM[0] = "minute";
            arrMeaningMM[1] = "month";
        }
    }
  
    let iM = 0;
    for (let i = 0; i < formatOrder.length; i++) {
        if (formatOrder[i] === "yyyy") {
            iYear = parseInt(match[i + 1]);
        } else if (formatOrder[i] === "mm") {
            if(arrMeaningMM[iM] === "month"){
                iMonth = parseInt(match[i + 1]) - 1;  // Month is 0-based in JavaScript
            }
            else{
                iMinute = parseInt(match[i + 1]);
            }    
            iM++;
        } else if (formatOrder[i] === "dd") {
            iDay = parseInt(match[i + 1]);
        } else if (formatOrder[i] === "hh") {
            iHour = parseInt(match[i + 1]);
        } else if (formatOrder[i] === "ss") {
            iSecond = parseInt(match[i + 1]);
        }
    }
    let date = new Date(iYear, iMonth, iDay, iHour, iMinute, iSecond);
    if (date.getFullYear() === iYear && date.getMonth() === iMonth && date.getDate() === iDay &&
        date.getHours() === iHour && date.getMinutes() === iMinute && date.getSeconds() === iSecond) {
        return date;
    }
    return false;
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*2021 update 2024 with copilot*/
string.dateToString = function(dtDate, sFormat) {
    if (typeof(dtDate.getMonth) !== "function") {
        return false;
    }
    sFormat = sFormat.toLowerCase();
    let sYear = String(dtDate.getFullYear());
    let sMonth = String(dtDate.getMonth() + 1);
    let sDay = String(dtDate.getDate());
    let sHour = String(dtDate.getHours());
    let sMinute = String(dtDate.getMinutes());
    let sSecond = String(dtDate.getSeconds());

    if (sMonth.length < 2) {
        sMonth = '0' + sMonth;
    }
    if (sDay.length < 2) {
        sDay = '0' + sDay;
    }
    if (sHour.length < 2) {
        sHour = '0' + sHour;
    }
    if (sMinute.length < 2) {
        sMinute = '0' + sMinute;
    }
    if (sSecond.length < 2) {
        sSecond = '0' + sSecond;
    }
    //let arrMeaningM = string.meaningM(sFormat);
    let parts = string.splitDateTimeFormatIntoParts(sFormat);
    let matches = sFormat.match(/mm/g);//xem có bao nhiêu token mm
    let countMM  = matches ? matches.length : 0;
    let arrMeaningMM = [];
    if(countMM === 1){
        if(parts["isDateFirst"] === true){
            arrMeaningMM[0] = "month";
        }
        else if(parts["isDateFirst"] === false){
            arrMeaningMM[0] = "minute";
        }
    }
    else if(countMM === 2){
        if(parts["isDateFirst"] === true){
            arrMeaningMM[0] = "month";
            arrMeaningMM[1] = "minute";
        }
        else if(parts["isDateFirst"] === false){
            arrMeaningMM[0] = "minute";
            arrMeaningMM[1] = "month";
        }
    }
    let sDate = sFormat.replace("yyyy", sYear)
                  .replace("dd", sDay)
                  .replace("hh", sHour)
                  .replace("ss", sSecond);
    for(let i=0;i<arrMeaningMM.length;i++){
        if(arrMeaningMM[i] === "month"){
            sDate = sDate.replace("mm",sMonth);
        }
        else{
            sDate = sDate.replace("mm",sMinute);
        }
    }      
    return sDate;       
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*
 * 
 * @param {type} str
 * @returns false nếu thất bại
 */
string.stringToDateStrYMD = function(sDate,sOrgFormat){
    let dtDate = string.stringToDate(sDate,sOrgFormat);
    if(dtDate === false){
        return false;
    }
    return string.dateToString(dtDate,"yyyy-mm-dd"); 
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
/*
 * https://stackoverflow.com/questions/46155/how-to-validate-an-email-address-in-javascript
 * @param {type} sEmail
 * @returns {Boolean}
 */
string.validateEmail = function(sEmail){
    const re = /^(mailto:)?(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    //return re.test(String(sEmail).toLowerCase());
    sEmail = String(sEmail).toLowerCase();
    if(re.test(String(sEmail).toLowerCase())===false){
        return false;
    }
    return sEmail;
}
/*------------------------------------------------------------------------------------------------------------------------------------*/
string.validURL = function(strURL){
    const pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
      '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
      '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
      '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
      '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
      '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
    return !!pattern.test(strURL);
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
string.prototype.validURI = function(protocols = []) {
    if (!Array.isArray(protocols) || protocols.length === 0) {
        throw new Error("Bạn phải truyền vào một mảng protocol, ví dụ ['http','https','mailto']");
    }

    // Tạo pattern regex cho các protocol
    const protoPattern = protocols.join('|'); // vd: "http|https|mailto"

    // Regex tổng quát
    const pattern = new RegExp(
      `^(${protoPattern}):` +        // protocol
      `(\\/\\/)?` +                  // // optional (mailto/tel không cần)
      `(` +
        `([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.[a-z]{2,}` + // domain name
        `|` +
        `(\\d{1,3}\\.){3}\\d{1,3}` +                    // OR IPv4
      `)` +
      `(\\:\\d+)?` +                 // port
      `(\\/[-a-z\\d%_.~+]*)*` +      // path
      `(\\?[;&a-z\\d%_.~+=-]*)?` +  // query string
      `(\\#[-a-z\\d_]*)?$`,'i'      // fragment
    );

    return pattern.test(this);
};
/*------------------------------------------------------------------------------------------------------------------------------------*/
function spellingVietnamese(str){
	/*Danh sách các cần ký tự gõ đúng : 
	"\\s{2,}","oà", "oá", "o?", "oã", "o?", "oè", "oé", "o?", "o?", "o?","??"	,"??"	,"??"
	Danh sách các c?m ký t? gõ sai    : 
	" ","òa", "òa", "?a", "õa", "?a", "òe", "óe", "?e", "õe", "?e","uo|?o|u?"  , "??|ù?|u?"
	*/
	var arrCorrect	= new Array(" ","o\u00E0", "o\u00E1", "o\u1EA3", "o\u00E3", "o\u1EA1", "o\u00E8", "o\u00E9", "o\u1EBB", "o\u1EBD", "o\u1EB9",
								"\u01B0\u01A1"	,"\u01B0\u1EDD","\u01B0\u01A1");
	var arrError	= new Array("\\s{2,}","\u00F2a", "\u00F2a", "\u1ECFa", "\u00F5a", "\u1ECDa", "\u00F2e", "\u00F3e", "\u1ECFe", "\u00F5e", "\u1ECDe",
								"uo|\u01B0o|u\u01A1","\u1EEB\u01A1|\u00F9\u01A1|u\u1EDD");
	var re;
	for(let i=0;i<arrError.length;i++){
		let re = new RegExp(arrError[i],"g");
		str=str.replace(re,arrCorrect[i]);
	}
	return str;
}
