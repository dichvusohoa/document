/*control_type và các data_type, format hiện nay
 * control_type: button         => data_type = control
 * control_type: checbox        => data_type = boolean
 * control_type: radio          => data_type = bất kể là kiểu gì
 * control_type: date           => data_type = date
 * control_type: combo          => data_type = tùy loại phụ thuộc type của key. Thường thì là int
 * control_type: link           
 * control_type: textbox        => data_type: int, number, email,url,string
 * control_type: textonly       => data_type: int, number, email,url,string
 * control_type: hierarchy      
 *data_type và các format, tham số phụ đi kèm
 *data_type = date đây là format hiển thị, mặc định là "dd/mm/yyyy"
 *data_type = number có kèm precision
 */
/* Reference
 * https://brainbell.com/javascript/making-resizable-table-js.html
 * https://www.w3schools.com/css/css3_backgrounds.asp
 * https://stackoverflow.com/questions/118241/calculate-text-width-with-javascript
 * 
 */
class autoTable{
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. Tạo table   
     * @param {json} response: dữ liệu để dựng bảng
     * */
    static arrAttBackground     = ["delete","err","selected","hover"];/*những attribute có background image hoặc color*/
    static arrClassBackground   = [];/*những class có background image hoặc color*/
    static arrClassControlButton = ["btn-icon-delete","btn-icon-add"];//các loại nút điều khiển trên bảng
    static arrHTMLDataTags       =  ["INPUT","TEXTAREA","A","SELECT"];//các loại thẻ HTML dùng để nhập liệu
    createHTMLTable(response){
        this.frmControl.style.display = "flex";
        this.tableData.style.display = "table";//vẫn theo kiểu truyền thống
        let isAllData =false;
        let htmlTHead = this.tableData.querySelector("thead");
        if(!htmlTHead || htmlTHead.innerHTML ==="" ){
            isAllData = true;
        }
        else{
            isAllData = false;
        }   
        let iFixedHeaderYPos =  this.atbts.hasOwnProperty("fixedHeaderYPos") ? this.atbts["fixedHeaderYPos"] : -1;
        let iFixedColumnXPos =  this.atbts.hasOwnProperty("fixedColumnXPos") ? this.atbts["fixedColumnXPos"] : -1;
        let iFixedColumnIdx = -1; 
        let isFixedColumnFromZero = true;
        if(iFixedColumnXPos >=0){ //có neo fixed column
            iFixedColumnIdx = this.atbts.hasOwnProperty("fixedColumnIdx") ? this.atbts["fixedColumnIdx"] : 0;
            isFixedColumnFromZero = this.atbts.hasOwnProperty("isFixedColumnFromZero") ? this.atbts["isFixedColumnFromZero"] : true;
        }
        let isPagination = this.atbts.hasOwnProperty("isPagination") ? this.atbts["isPagination"] : true;
        let iPageSize, arrPageSize,objPagingInfo;
        if(isPagination){//có dữ liệu phân trang
            iPageSize = response.info.paging.pageSize;
            arrPageSize = response.info.paging.listPageSize;
            objPagingInfo = response.info.paging;
        }
        else{
            iPageSize = null;
            arrPageSize = [];
            objPagingInfo = {"currentPage" :null,"numRow":Object.keys(response.info.data).length};
        }
        if(isAllData){
            this.createHTMLPaging(objPagingInfo);
            this.frmControl.querySelector("div.message").innerHTML = "";
            this.createHTMLColGroupAndHead(response.info.field,iFixedColumnIdx,isFixedColumnFromZero);
            this.createHTMLTBody(response.info.data,iFixedColumnIdx,isFixedColumnFromZero);
            this.setColumnOrder(this.nRowTHead);
            this.setColSelectResizeHeight(); //2023-11-12
            this.initEvent(iPageSize,arrPageSize,iFixedColumnXPos,iFixedColumnIdx,isFixedColumnFromZero,iFixedHeaderYPos); 
        }
        else{
            this.createHTMLPaging(objPagingInfo);
            //Begin 2022-03-14, 
            //Xóa bỏ createHTMLColGroupAndHead lý do hàm này tính lại initClientRectTop. Nó sẽ chạy không chính xác khi save khi scrollTop đang kéo nửa chừng
            //thay bằng dùng createDescriptionField. Lý do khi data load lại thì có thể 1 số column có combo có thể thay đổi defaultValue
            //cần gọi createDescriptionField để set lại defaultValue của các column này
            //this.createHTMLColGroupAndHead(response.info.field,iFixedHeaderYPos,iFixedColumnIdx,isFixedColumnFromZero);
            let arr =  this.createDescriptionField(response.info.field);
            this.keyFields  = arr[0];
            this.fields     = arr[1];
            this.nRowTHead  = arr[2];
            //End 2022-03-14
            this.frmControl.querySelector("div.message").innerHTML = "";
            this.createHTMLTBody(response.info.data,iFixedColumnIdx,isFixedColumnFromZero);
            this.setColumnOrder(this.nRowTHead);
            this.setColSelectResizeHeight(); //2023-11-12
        }
        let userRight = this.atbts.hasOwnProperty("userRight") ? this.atbts["userRight"] : 0;
        if(userRight & USER_RIGHT["update_right"]){
            this.selectRowByKey();
        }
        //console.log(this.tableData.innerHTML);
    };
    /*---------------------------------------------------------------------------------------------------*/
    async loadData(){
        this.frmControl.style.display = "flex";
        common.loadingStatus(this.frmControl,true,"Đang tải dữ liệu");
        let autoTblObj = this;
        try{
            let response = await fetch(this.sUrlGet);
            if(response.status !==200){
                throw Error (response.status + ". " + response.statusText);
            }
            let jsonRespData = await response.json();
            let sNode = autoTblObj.atbts["pathData"];
            
            //chạy chỉ để kiểm soát lỗi
            common.showUIAndControlError(autoTblObj.tableData,jsonRespData,
                function(){
                    //quét lỗi toàn data (jsonRespData) để chặn các lỗi hệ thống
                    //nếu không có lỗi thì gọi getCachedScripts, customRespRequest để tùy chỉnh lại dữ liệu 
                    autoTblObj.getCachedScripts(jsonRespData);
                    autoTblObj.customRespRequest(jsonRespData);
                },
                null, 
                true // throw Error để không chạy các lệnh dưới
            );
            /*Kiểm tra jsonRespData.hasOwnProperty(sNode) bởi vì cũng có thể có tình huống jsonRespData
            không có lỗi hệ thống, các part data khác có tồn tại nhưng riêng part data tại vị trí sNode
             lại không có vì lý do nào đó từ trên server*/
            if(jsonRespData.hasOwnProperty(sNode)){ 
                common.showUIAndControlError(autoTblObj.tableData,jsonRespData[sNode],function(){
                    autoTblObj.createHTMLTable(jsonRespData[sNode]);
                });
            }
            
            
            let frmControl = this.frmControl;//2022-07-12
            let inputSearch = frmControl["search"];
            inputSearch.value = "";
        }
        catch(error){
            console.log(error);
        }
        finally{
            common.loadingStatus(autoTblObj.frmControl,false);
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    async postData(data){
        common.loadingStatus(this.frmControl,true,"Đang upload dữ liệu");
        let autoTblObj = this;
        try{
            let sUrlPost = common.setURLParam(this.sUrlGet,"action","update");
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
            common.showUIAndControlError(autoTblObj.tableData,jsonRespData,function(){ 
                //không xử lý gì để nguyên jsonRespData
                },function(){ 
                    autoTblObj.showError(jsonRespData);
                },true //dừng không chạy tiếp phần redirect đằng sau nữa
            );
            //Từ đây trở xuống là xử lý redirect. Chú ý rằng status: server_incomplete cũng chạy được xuống dưới đây
            //Tuy nhiên với server_incomplete thì phần hiển thị kết quả ở dưới sẽ không chuẩn tuyệt đối
            if(jsonRespData.extra.return_row_id !==""){
                autoTblObj.sSelectedRowKey = jsonRespData.extra.return_row_id; //row sẽ focus vào khi load lại table
            }
            let sUrlGet = autoTblObj.sUrlGet;
            if(jsonRespData.extra.return_row_index !==""){
                let inputPageSize = autoTblObj.frmControl["page_size"];
                let iPageSize = common.isInteger(inputPageSize.value);
                let iNewPage = Math.floor(jsonRespData.extra.return_row_index/iPageSize);
                sUrlGet = common.setURLParam(autoTblObj.sUrlGet,"page",iNewPage);
            }
            if(jsonRespData.extra.return_param !==""){
                for(let prop in jsonRespData.extra.return_param){
                    sUrlGet = common.setURLParam(sUrlGet,prop,jsonRespData.extra.return_param[prop]);
                }
            }
            if(autoTblObj.arrDeletedCacheAfterPost.length>0){
                sUrlGet = common.setURLParam(sUrlGet,NOCACHE,autoTblObj.arrDeletedCacheAfterPost.join(LIST_SEPARATOR_CHAR));
            }
            autoTblObj.sUrlGet = sUrlGet;//2022-12-27 đặt lại sUrlGet của table theo địa chỉ mới
            //let response2 = await fetch(autoTblObj.sUrlGet);
            let response2 = await fetch(sUrlGet);
            if(response2.status !==200){
                throw Error (response2.status + ". " + response2.statusText);
            }
            let jsonRespData2 = await response2.json();
            common.showUIAndControlError(autoTblObj.tableData,jsonRespData2,
                function(){
                    //quét lỗi toàn data (jsonRespData2) để chặn các lỗi hệ thống
                    //nếu không có lỗi thì gọi getCachedScripts, customRespRequest để tùy chỉnh lại dữ liệu
                    autoTblObj.getCachedScripts(jsonRespData2);
                    autoTblObj.customRespRequest(jsonRespData2);//bỏ đi vì chuyển vào createHTMLTable từ 2024-01-26 rồi
                },
                null,// dùng xử lý ngầm định để show eror lên control
                true // throw Error để ngăn việc chạy các lệnh phía dưới
            );
            let sNode = autoTblObj.atbts["pathData"]; 
            if(jsonRespData2.hasOwnProperty(sNode)){ 
                /*check jsonRespData2.hasOwnProperty(sNode) vì muốn đề phòng trường hợp jsonRespData2 có thể không
                 lỗi và các part data khác không lôi nhưng riêng part data tại vị trí sNode có thể không có vì
                lý do nào đó trên server*/
                common.showUIAndControlError(autoTblObj.tableData,jsonRespData2[sNode],
                    function(){
                        autoTblObj.createHTMLTable(jsonRespData2[sNode]);
                        autoTblObj.showError(jsonRespData);
                        autoTblObj.saveCachedScripts(jsonRespData2);
                        autoTblObj.afterPostData(jsonRespData2);
                    }
                );
            }
        }
        catch(error){
            console.log(error);
        }
        finally{
            common.loadingStatus(autoTblObj.frmControl,false);
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. Tạo table   
     * @param {object} pagingInfo
     */
    createHTMLPaging(pagingInfo){
        let pPaging = this.frmControl.querySelector("p[name=paging]");
        let sPaging = "";
        if(pagingInfo.currentPage === null){//không phân trang
            this.frmControl.querySelector("SPAN").style.display = "none"; //xóa spam có chữ Số hàng đi
            pPaging.innerHTML = common.replaceTextMarkup(pPaging.innerHTML,"&nbsp;Tổng số: " + pagingInfo.numRow,"num_row");
            return;
            
        }
        sPaging = "&nbsp;&nbsp;&nbsp;Trang:";
        let sUrl = "";
        let sUrlGet = common.setURLParam(this.sUrlGet,NOCACHE,"");//xóa param NOCACHE . 2023-01-07
        for(let i=0;i<pagingInfo.numPage;i++){
            if(i === pagingInfo.currentPage*1.0){
                sPaging = sPaging + "<span name='current-page'>"+String(i+1)+"</span>&nbsp;&nbsp;";
            }
            else{
                //sUrl= common.setURLParam(this.sUrlGet,"page",i);
                sUrl= common.setURLParam(sUrlGet,"page",i);
                sPaging = sPaging + "<a href='" + sUrl +"'>" +String(i+1)+ "</a>&nbsp;&nbsp;";
            }
        }
        let sText = common.replaceTextMarkup(pPaging.innerHTML,sPaging,"paging");
        sText = common.replaceTextMarkup(sText,"Hàng từ " + pagingInfo.fromRow,"from_row");
        sText = common.replaceTextMarkup(sText," - " + pagingInfo.toRow,"to_row");
        sText = common.replaceTextMarkup(sText," / Tổng số: " + pagingInfo.numRow,"num_row");
        pPaging.innerHTML = sText;
        this.frmControl["page_size"].oldKey      = pagingInfo.pageSize;
        this.minPageSize = pagingInfo.minPageSize;
        this.maxPageSize = pagingInfo.maxPageSize;
    };
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. Kiểm tra xem 1 trường có phải là trường key
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
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. Kiểm tra xem 1 trường có phải là trường hidden
     * hay không   
     * @param {string} sFieldName
     * @param {object} fieldInfo
     */
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
    /*---------------------------------------------------------------------------------------------------*/
    /* Để thuận tiện cho việc lâp trình thì trong các COL không dùng span, nghĩa là dù nhiều COL gần nhau
     * có col_width bằng nhau thì vẫn tạo riêng rẽ ra các COL
     * 
     */
    createHTMLColGroup(fragmentColGroup){
        for(let idx=0;idx<this.fields.length;idx++){
            let htmlCol = document.createElement("COL");
            if(this.fields[idx].hasOwnProperty("col_width")){
                if(common.isInteger(this.fields[idx]["col_width"])){
                    htmlCol.style.width = this.fields[idx]["col_width"] + "px";
                }
                else{ //width có thể là %
                    htmlCol.style.width = this.fields[idx]["col_width"];
                }
                htmlCol.setAttribute("xIndex",idx);//2023-11-08, nhằm kết nối với TH element
            }
            fragmentColGroup.appendChild(htmlCol);
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    /*Set độ cao của các độ cao của các div.colSelectResize cho phù hợp. Hàm này được gọi khi độ cao của
     * table thay đổi như là thêm bớt các row data*/
    setColSelectResizeHeight(){
        let fHeight = this.tableData.offsetHeight;
        let divs    =  this.tableData.querySelectorAll("div.colSelectResize");
        divs.forEach(function(div) {
            div.style.height = fHeight + "px";
        });
    }
    /*---------------------------------------------------------------------------------------------------*/
    createHTMLTHead(fragmentTHead,iFixedColumnIdx,isFixedColumnFromZero){
        let actPainting = common.coloringTable(this.fields,this.nRowTHead);
        for(let r=0;r<this.nRowTHead;r++){
            let htmlRow = document.createElement("TR");
            for(let c=0;c<actPainting[r].length;c++){
                let action = actPainting[r][c];//r mean row index, c mean column index
                if(action){
                    let htmlTH = document.createElement("TH");
                    let sTitle = "";
                    if(action.color==="red"){    
                        sTitle = this.fields[c]["title"];
                        if(action.length>1){
                            htmlTH.setAttribute("rowspan",action.length);
                        }
                    }
                    else if(action.color==="blue"){
                        if(this.fields[c].hasOwnProperty("group_title") && this.fields[c]["group_title"][r]){
                            sTitle = this.fields[c]["group_title"][r];
                        }
                        if(action.length>1){
                            htmlTH.setAttribute("colspan",action.length);
                        }
                    }    
                    if(this.fields[c]["control_type"]==="button"){
                        htmlTH.innerHTML = sTitle;
                    }
                    else{
                        //phải sử dụng divTitle vì sau này resiable colwidth cho phép reisize col về = 0 và ẩn được text trên tiêu dề cột
                        let divTitle = document.createElement("DIV");
                        divTitle.innerHTML = sTitle;
                        htmlTH.appendChild(divTitle); 
                    }
                    if(iFixedColumnIdx >-1 && 
                        ((isFixedColumnFromZero && c <= iFixedColumnIdx) || (!isFixedColumnFromZero && c === iFixedColumnIdx) ) ){
                        htmlTH.setAttribute("fixed",c);
                    }
                    //Begin 2023-09-28, dùng xIndex để thay thế cho cellIndex trong javaScript, vì THEAD có thể có cell có thuộc tính colspan > 1 nên cellIndex sẽ không chính xác
                    htmlTH.setAttribute("xIndex",c);
                    //End 2023-09-28, dùng xIndex để thay thế cho cellIndex trong javaScript vì THEAD có thể có cell có thuộc tính colspan > 1 nên cellIndex sẽ không chính xác
                    //Begin 2023-11-05 resizeable
                    if(this.fields[c].hasOwnProperty("col_resizeable") && this.fields[c]["col_resizeable"] ===true){
                        let divResize = document.createElement("DIV");
                        divResize.className = "colSelectResize";
                        htmlTH.appendChild(divResize);
                    }
                    //End 2023-11-05 resizeable
                    htmlRow.appendChild(htmlTH);
                }
            }
            fragmentTHead.appendChild(htmlRow);
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    /*Với các filed có dạng radio button thường phải duplicate các column đó lên rồi bổ sung thêm một 
     * group_title cho column đầu tiên. Những thao tác này sẽ thực hiện trong function createDescriptionField
     * Tuy nhiên việc bổ sung group_title tức là trong THEAD của table phải thêm row có colspan nữa. Việc đó
     * yêu cầu phải tăng độ cao ( colspan) của các column khác. Thao tác điều chỉnh độ cao đó thực hiện
     * trong hàm adjustRowSpan này. 
     * 
     * @param {type} fieldInfo
     */
    adjustRowSpan(fieldInfo){
        for(let fieldName in fieldInfo){
            if(this.isHiddenField(fieldName,fieldInfo)){
                continue;//không xét trường ẩn
            }
            let fieldDetail = fieldInfo[fieldName];
            let iRowSpan = fieldDetail.hasOwnProperty("rowspan")? fieldDetail["rowspan"]*1.0:1; 
            /*Chỉ điều chỉnh độ cao khi iRowSpan=1*/
            if(fieldDetail["control_type"]==="radio" && iRowSpan<=1){ 
                for(let key in fieldInfo){
                    if(this.isHiddenField(fieldName,fieldInfo)){
                        continue;//không xét trường ẩn
                    }
                    let iRow = fieldInfo[key].hasOwnProperty("rowspan")? fieldInfo[key]["rowspan"]*1.0:1; 
                    fieldInfo[key]["rowspan"] = iRow + 1;
                }
                
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    /*Từ object fieldInfo trả về [arrKeyField,arrFiled,nRowTHead]. Ngoài ra function cũng kiểm tra userRight
     * mà bổ sung thêm các control field vào cho arrFiled trong kết quả trả về. Với các field dữ liệu dạng radio
     * trong fieldInfo thì hàm sẽ chuyển sang dạng nhiều column trong arrField trả về
     * @param {object} fieldInfo
     * @returns [arrKeyField,arrFiled,nRowTHead]
     * arrKeyField là array các key field, arrFiled là array các field dữ liệu, nRowTHead là số row span của THEAD
     */
    createDescriptionField(fieldInfo){
        this.adjustRowSpan(fieldInfo);
        let arrFiled = [];
        let arrKeyField = [];
        let idx = 0;
        let nRowTHead = 0;
        for(let fieldName in fieldInfo){
            if(this.isKeyField(fieldName,fieldInfo)){
                arrKeyField.push(fieldName);
            }
            if(this.isHiddenField(fieldName,fieldInfo)){
                continue;//không xét trường ẩn
            }
            let fieldDetail = fieldInfo[fieldName];
            let iHeight = fieldDetail.hasOwnProperty("rowspan")? fieldDetail["rowspan"]*1.0:1; 
            if(nRowTHead<iHeight){
                nRowTHead = iHeight;
            }
            if(fieldDetail["control_type"]==="radio"){
                let iRowSpan = fieldDetail.hasOwnProperty("rowSpan") ? fieldDetail["rowSpan"]*1.0 - 1 : 1.0;
                let idx_1 = idx;
                let sTitle = fieldDetail["title"];
                for(let key in fieldDetail["listData"]){
                    arrFiled[idx] = JSON.parse(JSON.stringify(fieldDetail));
                    arrFiled[idx]["field"] = fieldName;
                    arrFiled[idx]["title"] = fieldDetail["listData"][key];
                    arrFiled[idx]["selectedKey"] = key;//lưu key của column hiện thời
                    if(iRowSpan <= 1){
                        delete arrFiled[idx]["rowspan"];
                    }
                    else{
                        arrFiled[idx]["rowspan"] = iRowSpan;
                    }
                    if(idx === idx_1){
                        if(arrFiled[idx_1].hasOwnProperty("group_title")){
                            arrFiled[idx_1]["group_title"].push(sTitle);
                        }
                        else{
                            arrFiled[idx_1]["group_title"] = [sTitle];
                        }
                    }
                    else{
                        delete arrFiled[idx]["group_title"];
                    }
                    idx++;
                }
                
            }
            else{
                arrFiled[idx] = fieldDetail;
                arrFiled[idx]["field"] = fieldName;
                idx++;
            }
        }
        let userRight = this.atbts.hasOwnProperty("userRight") ? this.atbts["userRight"] : 0;
        if(userRight & USER_RIGHT["add_right"]){
            let addObj = {"field":"add","title":"<input type='button' class='btn-icon-add'>","control_type":"button","data_type":"control","class":"btn-icon-add","col_width":"2.5rem","rowspan":nRowTHead};
            arrFiled.unshift(addObj);
        }
        if(userRight & USER_RIGHT["delete_right"]){
            let delObj  = {"field":"delete","title":"<input type='button' class='btn-icon-delete'>","control_type":"button","data_type":"control","class":"btn-icon-delete","col_width":"2.5rem","rowspan":nRowTHead};
            arrFiled.unshift(delObj);
        }
        let orderObj = {"field":"order","title":"#","control_type":"textonly","data_type":"int","col_width":"2.5rem","rowspan":nRowTHead};
        arrFiled.unshift(orderObj);
        return [arrKeyField,arrFiled,nRowTHead];
    }
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. tạo Table Head và COLGROUP
     * hay không   
     * @param {object} fieldInfo: mô tả các côt dữ liệu
     **/
    //createHTMLColGroupAndHead(fieldInfo,iFixedColumnXPos,iFixedColumnIdx,isFixedColumnFromZero,iFixedHeaderYPos){
    createHTMLColGroupAndHead(fieldInfo,iFixedColumnIdx,isFixedColumnFromZero){
        let fragmentColGroup    = document.createDocumentFragment();
        let fragmentTHead       = document.createDocumentFragment();
        let arr =  this.createDescriptionField(fieldInfo);
        this.keyFields  = arr[0];
        this.fields     = arr[1];
        this.nRowTHead =  arr[2];
        //2023-11-05 xóa bỏ việc createHTMLColGroup lý do vì khi đặt col_width vào col_group thì không resizie được
        this.createHTMLColGroup(fragmentColGroup);
        this.createHTMLTHead(fragmentTHead,iFixedColumnIdx,isFixedColumnFromZero);
        let htmlColGroup = this.tableData.querySelector("colgroup");
        if(!htmlColGroup){
            htmlColGroup = document.createElement("COLGROUP");
            htmlColGroup.appendChild(fragmentColGroup);
            this.tableData.appendChild(htmlColGroup);
        }
        let htmlTHead = this.tableData.querySelector("thead");
        if(!htmlTHead){
            htmlTHead = document.createElement("THEAD");
            htmlTHead.appendChild(fragmentTHead);
            this.tableData.appendChild(htmlTHead);
        }
        let htmlTH;
        if(iFixedColumnIdx>-1){
            if(isFixedColumnFromZero){
                htmlTH = this.tableData.querySelectorAll("TH")[0];//column 0
            }
            else{
                htmlTH = this.tableData.querySelectorAll("TH")[iFixedColumnIdx];//column ngoài cùng bên phải
            }
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    /*function for autoTable object.
     * Trong function body dùng this hay objAutoTable đều được. tạo 1 row data
     * hay không   
     * @param {object} fieldInfo: mô tả các côt dữ liệu
     * @param {object} 1 dòng dữ liệu
     **/
    createHTMLTRow(rowData,iRightMostFixedColumn,isFixedColumnFromZero){
        let sNameRow = "";
        for(let fieldName of this.keyFields){
            sNameRow = sNameRow + LIST_SEPARATOR_CHAR + rowData[fieldName];
        }
        let  reExp = new RegExp("^[" + LIST_SEPARATOR_CHAR +"]","i");
        //sNameRow = sNameRow.replace(/^#/,'');
        sNameRow = sNameRow.replace(reExp,'');
        let htmlTR = document.createElement("TR");
        htmlTR.setAttribute("name",sNameRow);
        for(let idx =0; idx < this.fields.length;idx++){
            let sType =  this.fields[idx]["control_type"];
            let sDataType = this.fields[idx].hasOwnProperty("data_type") ? this.fields[idx]["data_type"] : "string";
            let iMaxLength = this.fields[idx].hasOwnProperty("maxlength") ? this.fields[idx]["maxlength"] : -1;
            let fieldName = this.fields[idx]["field"];
            let htmlTD  = document.createElement("TD");
            if( (iRightMostFixedColumn > -1) && 
                ((isFixedColumnFromZero && idx <= iRightMostFixedColumn)||(!isFixedColumnFromZero && idx === iRightMostFixedColumn))){
                htmlTD.setAttribute("fixed",idx);
            }
            if(idx===0){
                htmlTD.className = "colOrder";
            }
            //begin: tạo control trong TD
            let control=null; 
            if(sType==="link"){
                let jsonLink = JSON.parse(rowData[fieldName]);
                control = editLink.createHtmlA(jsonLink);
            }
            //else if(sType !== "textonly"){
            else if(["button", "textbox", "combo", "date", "checkbox", "radio"].includes(sType)){
                control = document.createElement("INPUT");
                if(sType ==="combo" || sType ==="date" || sType ==="textbox" ){
                    if(iMaxLength>0){
                        control.setAttribute("maxlength",iMaxLength);
                    }
                }
            }
            else if(sType === "hierarchy"){
                let arrLocation = JSON.parse(rowData[fieldName]);
                let objAtbts = {
                    paramDataNames:this.fields[idx]["paramDataNames"],
                    paramUrlNames:this.fields[idx]["paramUrlNames"]};
                control = editHierarchy.createHtmlASequence(arrLocation,this.fields[idx]["baseUrl"],objAtbts);
            }
            //end: tạo control trong TD
            if(sType==="link"){
                htmlTD.className = "edit-link";
            }
            else if(sType==="button"){
                control.type = sType;
                control.className  = this.fields[idx]["class"];
                if(autoTable.arrClassControlButton.filter( //bổ sung 2023-02-28
                    (item) => control.classList.contains(item.toString())).length
                ){
                    htmlTD.className = "colControl";
                }
            }
            else if(sType==="textbox"){
                control.setAttribute("data_type",sDataType);
                if(rowData[fieldName]===null){//đây là trường hợp filed DEFAULT IS NULL và khi không nhập giá trị thì có giá trị NULL
                    control.oldValue = null;
                    control.value = "";// input thì không nhận NULL được phải dùng "" để thay thế
                }
                else if(sDataType === "int"){
                    control.oldValue = common.isInteger(rowData[fieldName]);
                    if(control.oldValue ===false){
                        control.value = rowData[fieldName];//sai format để nguyên
                    }
                    else{
                        control.value = common.numberWithCommas(control.oldValue);
                    }
                }
                else if(sDataType === "number"){
                    let iPrecision = this.fields[idx].hasOwnProperty("precision") ? this.fields[idx]["precision"] : 0;
                    control.oldValue = common.roundNumber(rowData[fieldName],iPrecision);
                    if(control.oldValue ===false){
                        control.value = rowData[fieldName];//sai format để nguyên
                    }
                    else{
                        control.value = common.numberWithCommas(control.oldValue);
                    }
                }
                else{
                    control.value = rowData[fieldName];
                    control.oldValue = control.value;
                }
                //control.style.width = String(this.fields[idx]["col_width"]) + "px";
            }
            else if(sType==="combo"){
                //thêm thuộc tính type này để chống căn lề phải trong CSS nếu như key combo là dạng number
                control.setAttribute("type",sType);
                control.setAttribute("data_type",sDataType);
                if(rowData[fieldName]===null){
                    control.oldKey = null;
                    control.key = null;
                    control.value = "";//vì input.value không thể đặt = null duoc
                    control.oldValue = null;
                }
                else{
                    control.oldKey = rowData[fieldName];
                    control.key = rowData[fieldName];
                    control.value = this.fields[idx]["listData"][control.key];
                    control.oldValue = control.value;
                }
            }
            else if(sType==="date"){
                let sShowDateFormat = this.fields[idx].hasOwnProperty("format") ? this.fields[idx]["format"] : "dd/mm/yyyy";
                control.setAttribute("data_type",sDataType);  
                if(rowData[fieldName]===null){//đây là trường hợp filed DEFAULT IS NULL và khi không nhập giá trị thì có giá trị NULL
                    control.oldValue = null;
                    control.value = "";// input thì không nhận NULL được phải dùng "" để thay thế
                }
                else{
                    let dtValue = new Date(rowData[fieldName]);
                    if(dtValue.toString()==="Invalid Date"){
                        control.value = rowData[fieldName];//sai format thì để nuyên
                        control.oldValue = false;
                    }
                    else{
                        control.value = string.dateToString(dtValue,sShowDateFormat);
                        control.oldValue = string.dateToString(dtValue,"yyyy-mm-dd");
                    }
                }
            }
            else if(sType==="checkbox"){
                control.type = sType;
                control.checked = rowData[fieldName]*1.0;
                control.oldValue = control.checked;
                htmlTD.className = "colCheckbox";
            }
            else if(sType==="radio"){
                control.type = sType;
                control.name = fieldName + "_" +sNameRow;
                control.value = this.fields[idx]["selectedKey"];
                control.checked = (rowData[fieldName] == control.value);
                //nếu control không checked thì oldValue của radio không có nghía gì (NULL)
                control.oldValue = control.checked ? control.value : null;
                htmlTD.className = "colRadio";
            }
            else if(sType==="textonly"){
                if(!rowData.hasOwnProperty(fieldName)){
                    htmlTD.innerHTML = "";
                }
                else if(sDataType === "int"){
                    let fVal = common.isInteger(rowData[fieldName]);
                    if(fVal === false){
                        htmlTD.innerHTML = rowData[fieldName];//sai format để nguyên
                    }
                    else{
                        htmlTD.innerHTML = common.numberWithCommas(fVal);
                    }
                    htmlTD.setAttribute("txt-align","right");
                }
                else if(sDataType === "number"){
                    let iPrecision = this.fields[idx].hasOwnProperty("precision") ? this.fields[idx]["precision"] : 0;
                    let fVal = common.roundNumber(rowData[fieldName],iPrecision);
                    if(fVal === false){
                        htmlTD.innerHTML = rowData[fieldName];//sai format để nguyên
                    }
                    else{
                        htmlTD.innerHTML = common.numberWithCommas(fVal);
                    }
                    htmlTD.setAttribute("txt-align","right");
                }
                else{
                    htmlTD.innerHTML = rowData[fieldName];
                    htmlTD.setAttribute("txt-align","left");
                }
            }
            else if(sType==="hierarchy"){
                //đặt className này để tuy chưa khởi tạo control nhưng hiện thị dữ liệu, các thẻ A cho đúng Format
                htmlTD.className = "edit-hierarchy";
            }
            if(control!==null){
                htmlTD.appendChild(control);
            }
            htmlTR.appendChild(htmlTD);
        }
        return htmlTR;
    };
    /*---------------------------------------------------------------------------------------------------*/
    createHTMLTBody(tableData,iFixedColumnIdx,isFixedColumnFromZero){
        let htmlTBody = this.tableData.querySelector("tbody");
        if(!htmlTBody){
            htmlTBody = document.createElement("TBODY");
            this.tableData.appendChild(htmlTBody);
        }
        let htmlTRs =htmlTBody.querySelectorAll("TR");
        //xóa các dòng cũ
        for (let i = 0; i < htmlTRs.length; i++) {
            htmlTRs[i].remove();
        }
        let fragment = document.createDocumentFragment();
        for(let i in tableData){
            let htmlRow= this.createHTMLTRow(tableData[i],iFixedColumnIdx,isFixedColumnFromZero);
            fragment.appendChild(htmlRow);
        }
        htmlTBody.appendChild(fragment);
    };
    /*---------------------------------------------------------------------------------------------------*/
    getValueTextBox(idxCol,htmlCell){
        let fields = this.fields;
        let sType = fields[idxCol]["control_type"];
        let sDataType = fields[idxCol].hasOwnProperty("data_type") ? fields[idxCol]["data_type"] : "string";
        let defaultValue =  fields[idxCol].hasOwnProperty("defaultValue")? fields[idxCol]["defaultValue"] : null;
        let control   = htmlCell.querySelector("INPUT"); 
        let constraints = fields[idxCol].hasOwnProperty("constraints") ? fields[idxCol]["constraints"] : null;
        let isRequired = constraints && constraints.hasOwnProperty("required") && constraints["required"];
        control.value = control.value.trim(); 
        
        if(sType!=="textbox"){
            return {"unknown_error":ERR_DATA["unknown_error"]};;
        }
        if(control.value===""){
            if(isRequired === false){
                return null;
            }
            else if(defaultValue){
                return defaultValue;
            }
            else{
                return {"required":ERR_DATA["required"]};
            }
        }
        let returnValue = null;
        if(sDataType === "int"){
            returnValue=  common.isInteger(control.value);
        }
        else if(sDataType === "number"){
            let iPrecision = fields[idxCol].hasOwnProperty("precision") ? fields[idxCol]["precision"] : 0;
            returnValue = common.roundNumber(control.value,iPrecision);
        }
        else if(sDataType === "email"){
            returnValue = string.validateEmail(control.value);
        }
        else if(sDataType === "url"){
            returnValue = string.validURL(control.value);
        }
        if(returnValue === null){//sDataType không phải là loại int,number,email,url
            return control.value; 
        }
        else if(returnValue === false){ //có lỗi
            return {sDataType: ERR_DATA.sDataType};
        }
        else{
            return returnValue;
        }
        /*    return control.value;     
        }*/
    }
    /*---------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {int} idxCol
     * @param {htmlControl} control
     * return value:
     * NULL nếu control is blank và không bị ràng buộc điều kiện required = true
     * checkbox: return true, false
     * radio: return value
     * combo: return key, nếu không search ra giá trị return ""
     * date,int,number,email,url: return false nếu sai định dạng
     */
    getKeyOrValue(idxCol,htmlCell){
        let fields = this.fields;
        let sType = fields[idxCol]["control_type"];
        if(sType === "textonly"){
            return htmlCell.textContent;
        }
        else if(sType === "link"){
            return htmlCell.querySelector("A").textContent;
        }
        let control   = htmlCell.querySelector("INPUT"); 
        let constraints = fields[idxCol].hasOwnProperty("constraints") ? fields[idxCol]["contraints"] : null;
        if(sType==="checkbox"){
            return control.checked;
        }
        else if(sType==="radio"){
            return control.value;
        }
        else if(sType==="combo"){
            control.value = control.value.trim(); 
            let sSearchType = fields[idxCol].hasOwnProperty("search_type") ? fields[idxCol]["search_type"] : "";
            let defaultKey =  fields[idxCol].hasOwnProperty("defaultKey")? fields[idxCol]["defaultKey"] : null;
            if(sSearchType === "dict_tree"){
                return treeSearchCombo.getTreeCmbKey(control.value,fields[idxCol]["listData"],fields[idxCol]["treeData"],defaultKey,constraints);
            }
            else{
                return searchCombo.getCmbKey(control.value,fields[idxCol]["listData"],defaultKey,constraints);
            }
        }
        else if(sType==="date"){
            control.value = control.value.trim(); 
            let sShowDateFormat = fields[idxCol].hasOwnProperty("format") ? fields[idxCol]["format"] : "dd/mm/yyyy";
            let defaultValue =  fields[idxCol].hasOwnProperty("defaultValue")? fields[idxCol]["defaultValue"] : null;
            return datePicker.getDatePickerValue(control.value,sShowDateFormat,defaultValue,constraints);
        }
        else if(sType === "editHierarchy"){
            return editHierarchy.getEditHierarchyValue(htmlCell);
        }
        else if(sType === "textbox"){
            control.value = control.value.trim(); 
            return this.getValueTextBox(idxCol,htmlCell);
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    /*Hàm này dùng cho hàm filterTable nó chỉ lấy nội dung bề mặt của các cell chứ không kiểm soát contrains
     * và format sâu như hàm getKeyOrValue
     * 
     * @param {integer} idxCol
     * @param {htmlCell} htmlCell
     * @returns {Nội dung của cell. Nếu cell chứa các control như là date, combo... thì trả về nội dung bề mặt
     * của các control đó mà không kiểm soát contrains và bắt lỗi format}
     */
    getContentOfCell(idxCol,htmlCell){
        let fields = this.fields;
        let sType = fields[idxCol]["control_type"];
        if(sType === "textonly"){
            return htmlCell.textContent;
        }
        else if(sType === "link"){
            return htmlCell.querySelector("A").textContent;
        }
        else if(sType === "textbox" || sType === "date" || sType === "combo"){
            return htmlCell.querySelector("INPUT").value;
        }
        else{ //button, checkbox,radio
            return null;
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    /* Kiểm tra xem thẻ htmlObj chứa dữ liệu (loại: input,textfragment,select,a) có phải là một tag nằm trực tiếp 
     * trong TD cell hay không, để phân biệt với trường hợp htmlObj nằm trong các control phức tạp như là searchCombo
     * datePicker hay editHierarchy
     * @param {type input or A,TEXTAREA,INPUT,SELECT } htmlObj
     * @param {type TBODY } tBodyObj
     * @returns {string}: true or false
     */
    static isSimpleDataTagInCell(htmlObj){
        if(autoTable.arrHTMLDataTags.includes(htmlObj.tagName)&& //các loại thẻ data cho phép 
            htmlObj.parentNode.parentNode.tagName === "TR"){ // htmlObj nằm trực tiếp trong cell TD
            let htmlCell = htmlObj.parentNode;    
            if(!htmlCell.classList.contains("edit-hierarchy")){
                return true;// htmlObj nằm trực tiếp trong TD cell không bị control editHierarchy vây quanh 
            }
            //From here: xét riêmg trường  hợp TD có class edit-hierarchy
            if(htmlCell.querySelector(":scope>input[type='button']")){ //đã khởi tạo control editHierarchy nên có nút input type = button ở trong (nút delete)
                return false;
            }
            return true;// tuy TD có class edit-hierarchy nhưng chưa khởi tạo control
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------*/
    static isInputDestInCombo(htmlObj){
        if( htmlObj.tagName === "INPUT" &&  htmlObj.type !== "button" &&
            htmlObj.parentNode.classList.contains("search-combo") &&    
            htmlObj.parentNode.parentNode.parentNode.tagName === "TR"){           
            return true;
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------*/
    static isInputDestInDatePicker(htmlObj){
        if( htmlObj.tagName === "INPUT" && htmlObj.type !== "button" && 
            htmlObj.parentNode.classList.contains("date-picker") &&    
            htmlObj.parentNode.parentNode.parentNode.tagName === "TR"){            
            return true;
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------*/
    static isInEditHierarchy(htmlObj){
        let isAObj = htmlObj.tagName === "A" && htmlObj.parentNode.classList.contains("edit-hierarchy");
        let isDeleleBtn = htmlObj.tagName === "INPUT" && htmlObj.type === "button" && htmlObj.parentNode.classList.contains("edit-hierarchy");
        let isInputDest = htmlObj.tagName === "INPUT" && htmlObj.type !== "button" && htmlObj.parentNode.tagName === "DIV" && htmlObj.parentNode.parentNode.classList.contains("edit-hierarchy");
        if( isAObj || isDeleleBtn || isInputDest){            
            return true;
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------*/
    formSubmit = (e)=>{
        e = e ? e : window.event;
        e.preventDefault();
        this.save();
    }
    /*---------------------------------------------------------------------------------------------------*/
    pagingClick = (e)=>{
        e = e ? e : window.event;
        if(e.target.tagName !== "A"){
            return;
        }
        e.preventDefault();
        if(this.confirmExitTable()){
            this.sUrlGet = e.target.href;
            this.loadData();
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTHeadClick = (e,iFixedColumnIdx,isFixedColumnFromZero)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "INPUT"){
            if(e.target.classList.contains('btn-icon-delete')){
                this.delAll();
            }
            else if(e.target.classList.contains('btn-icon-add')){
                this.addRow(null,iFixedColumnIdx,isFixedColumnFromZero);
            }
        }
        else if( e.target.tagName === "TH" || //click vào TH 
            ( e.target.parentNode.tagName === "TH" && e.target.className!=="colSelectResize") // click vào divTitle chứa title của column chứ không phải là div.colSelectResize
        ){
            let htmlTH = e.target.tagName === "TH" ? e.target : e.target.parentNode;
            let iCellIndex  =   parseInt(htmlTH.getAttribute("xIndex"));
            let sColSpan   =   htmlTH.getAttribute("colSpan");
            let sType =  this.fields[iCellIndex]["control_type"];
            if((iCellIndex !==0 ) &&  // không phải là column order 1,2,3 ỏ đầu 
                (sType !== "button")&& // không phải cột các nút điều khiển
                (!sColSpan || sColSpan === "1" || sColSpan === 1)){
                this.selectColumn(iCellIndex);
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTHeadMouseOver =(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "DIV" && e.target.className === "colSelectResize"){//chọn div.colSelectResize để resize column
            e.target.setAttribute("hover","");
        }
        else if(e.target.tagName === "TH" || e.target.parentNode.tagName === "TH"){ //chọn TH
            let htmlTH = e.target.tagName === "TH" ? e.target : e.target.parentNode;
            let iCellIndex  =   parseInt(htmlTH.getAttribute("xIndex"));
            let sColSpan   =   htmlTH.getAttribute("colSpan");
            let sType =  this.fields[iCellIndex]["control_type"];
            if((iCellIndex !==0 ) &&  // không phải là column order 1,2,3 ỏ đầu 
                (sType !== "button")&& // không phải cột các nút điều khiển
                (!sColSpan || sColSpan === "1" || sColSpan === 1)){ //không phải là các cell có rowSpan >=2
                htmlTH.setAttribute("hover","");
                this.setMultiBackgrounds(htmlTH);
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTHeadMouseOut =(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "DIV" && e.target.className === "colSelectResize"){//chọn div.colSelectResize để resize column
            e.target.removeAttribute("hover");
        }
        else if(e.target.tagName === "TH" || e.target.parentNode.tagName === "TH"){
            let htmlTH = e.target.tagName === "TH" ? e.target : e.target.parentNode;
            let iCellIndex  =   parseInt(htmlTH.getAttribute("xIndex"));
            let sColSpan   =   htmlTH.getAttribute("colSpan");
            let sType =  this.fields[iCellIndex]["control_type"];
            if((iCellIndex !==0 ) &&  // không phải là column order 1,2,3 ỏ đầu 
                (sType !== "button")&& // không phải cột các nút điều khiển
                (!sColSpan || sColSpan === "1" || sColSpan === 1)){ //không phải là các cell có rowSpan >=2
                if(htmlTH.hasAttribute("hover")){
                    htmlTH.removeAttribute("hover","");
                    this.setMultiBackgrounds(htmlTH);
                }
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTHeadMouseDown =(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "DIV" && e.target.className === "colSelectResize"){
            let xIndex = parseInt(e.target.parentNode.getAttribute("xIndex"));
            let col = this.tableData.querySelectorAll("COL")[xIndex];
            this.currentResizeCol  = col;
            this.currentResizeColWidth  = col.offsetWidth; 
            this.currentPageX = e.pageX; //tọa độ X của mouse
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    documentMouseMove =(e)=>{
        e = e ? e : window.event;
        if( this.currentResizeCol &&   //dùng thêm các điều kiện lọc này để lọc bớt khi không mousemove trên đúng các TH của table
            this.currentResizeColWidth && 
            this.currentPageX){ 
            let diffX = e.pageX - this.currentPageX;
            let colWidth = this.currentResizeColWidth + diffX;
            this.currentResizeCol.style.width = colWidth+'px';
            let xIndex = this.currentResizeCol.getAttribute("xIndex");
            let htmlCell = this.tableData.querySelector(`TH[xIndex='${xIndex}']:not([colSpan]), TH[xIndex='${xIndex}'][colSpan='1']`);
            let divTitle = htmlCell.firstChild;
            if(divTitle && divTitle.tagName === "DIV"){
                divTitle.style.width = colWidth+'px'; /*set độ rộng div chứa title của cột*/
                divTitle.style.whiteSpace = "nowrap";
            }
            let divColSelectResize = htmlCell.querySelector("div.colSelectResize");
            if(divColSelectResize){
                divColSelectResize.setAttribute("hover","");
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    documentMouseUp =(e)=>{
        e = e ? e : window.event;
        if(this.currentResizeCol){
            let xIndex = this.currentResizeCol.getAttribute("xIndex");
            let htmlCell = this.tableData.querySelector(`TH[xIndex='${xIndex}']:not([colSpan]), TH[xIndex='${xIndex}'][colSpan='1']`);
            let divColSelectResize = htmlCell.querySelector("div.colSelectResize");
            if(divColSelectResize){
                divColSelectResize.removeAttribute("hover");
            }
            let divTitle = htmlCell.firstChild;
            if(divTitle && divTitle.tagName === "DIV"){
                divTitle.style.whiteSpace = "normal"; // lại cho phép wrap title
            }
            this.currentResizeCol  = null;
            this.currentResizeColWidth  = null; 
            this.currentPageX = null;
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTBodyClick = (e,iFixedColumnIdx,isFixedColumnFromZero)=>{
        e = e ? e : window.event;
        let control = e.target;
        if(control.tagName === "TD"){
            if(control.className ==="colOrder"){
                this.selectRow(control.parentNode);
            }
        }
        else if(control.tagName === "INPUT" || control.tagName === "TEXTAREA"){
            let row=control.closest("tr");
            //let tableTBody = row.parentNode;
            if(control.classList.contains('btn-icon-add')){
                this.addRow(row,iFixedColumnIdx,isFixedColumnFromZero);
            }
            else if(control.classList.contains('btn-icon-delete')){
                this.delRow(row);
            }
            /*else if(control.type ==="text" && autoTable.isSimpleDataTagInCell(control,tableTBody)){//input nằm trực tiếp trong cell
                this.inputClick(e);
            } */
            else if(control.type ==="text"){//input nằm trực tiếp trong cell
                this.inputClick(e);
            } 
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTBodyDblClick = (e)=>{
        e = e ? e : window.event;
        let control = e.target;
        /*let tableTBody = this.tableData.querySelector("TBODY");
        if(control.tagName === "INPUT" && control.type === "text" && 
            autoTable.isSimpleDataTagInCell(control,tableTBody)){//input nằm trực tiếp trong cell
            this.inputDblClick(e);
        }*/
        if(control.tagName === "INPUT" && control.type === "text"){
            this.inputDblClick(e);
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    tableTBodyFocusIn =(e,iFixedColumnIdx,isFixedColumnFromZero)=>{
        e = e ? e : window.event;
        let control = e.target;
       // let tableTBody = this.tableData.querySelector("TBODY");
        if(autoTable.isSimpleDataTagInCell(control)){ //loại bỏ khi nằm trong các control phức tạp như combo, date,editHierarchy
            this.inputAFocusIn(e,iFixedColumnIdx,isFixedColumnFromZero);
        }
    };
    /*---------------------------------------------------------------------------------------------------*/ 
    /*Xóa tình trạng delete của row nếu có change value của các INPUT*/
    tableTBodyChange=(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "INPUT"){
            let row = e.target.closest("tr");
            if(row.hasAttribute("delete")){
                row.removeAttribute("delete");
                let controls =row.querySelectorAll("td[delete], input[delete]");
                let autoTblObj = this;
                controls.forEach(function(control) {
                    control.removeAttribute("delete");
                    autoTblObj.setMultiBackgrounds(control);
                });
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/    
    tableTBodyMouseOver=(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "TD" && e.target.className === "colOrder"){
            e.target.setAttribute("hover","");
            this.setMultiBackgrounds(e.target);
        }
    }
    /*---------------------------------------------------------------------------------------------------*/ 
    tableTBodyMouseOut=(e)=>{
        e = e ? e : window.event;
        if(e.target.tagName === "TD" && e.target.className === "colOrder"){
            if(e.target.hasAttribute("hover")){
                e.target.removeAttribute("hover");
                this.setMultiBackgrounds(e.target);
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/ 
    initEvent(iPageSize,arrPageSize,iFixedColumnXPos,iFixedColumnIdx,isFixedColumnFromZero,iFixedHeaderYPos){
        //Begin Event form control
        let frmControl = this.frmControl;
        let autoTblObj = this;
        frmControl.addEventListener("submit",this.formSubmit);
        let inputPageSize = frmControl["page_size"];
        if(iPageSize !== null){//có phân trang
            inputPageSize.value = iPageSize;
            let smallCboPageSize = new searchCombo(inputPageSize,arrPageSize,null,{"sFunctSelectItem":autoTblObj.pageSize});
            this.smallCboPageSize = smallCboPageSize;
            let pPaging = frmControl.querySelector("p[name=paging]");
            pPaging.addEventListener("click",this.pagingClick);
        }
        else{//không phân trang
            inputPageSize.style.display = "none";
        }
        let inputSearch = frmControl["search"];//2022-07-12
        inputSearch.addEventListener("keyup",this.filterTable);
        //End Event form control
        //Begin Event table
        let tableTHead = this.tableData.querySelector("THEAD");
        tableTHead.addEventListener("click",function(e){
            autoTblObj.tableTHeadClick(e,iFixedColumnIdx,isFixedColumnFromZero);
        });
        /*không dùng mouseenter vì nó không bubble nên sẽ không có event tại TH chỉ có tại THEAD*/
        tableTHead.addEventListener("mouseover",this.tableTHeadMouseOver);
        tableTHead.addEventListener("mouseout",this.tableTHeadMouseOut);
        //begin 2023-11-05 resiable column
        tableTHead.addEventListener("mousedown",this.tableTHeadMouseDown);
        //cái này buộc phải addEventListener ở mousemove vì sự kiện này không nhất thiết xảy ra  tại tableTHead
        document.addEventListener("mousemove",this.documentMouseMove);
        //cái này buộc phải addEventListener ở mouseup vì sự kiện này không nhất thiết xảy ra  tại tableTHead
        document.addEventListener("mouseup",this.documentMouseUp);
        //end 2023-11-05 resiable column
        let tableTBody = this.tableData.querySelector("TBODY");
        tableTBody.addEventListener("click",this.tableTBodyClick);
        tableTBody.addEventListener("focusin",function(e){
            autoTblObj.tableTBodyFocusIn(e,iFixedColumnIdx,isFixedColumnFromZero);
        });
        tableTBody.addEventListener("dblclick",this.tableTBodyDblClick);
        frmControl.addEventListener("keydown",this.keyDown);          
        tableTBody.addEventListener("keydown",this.keyDown);
        tableTBody.addEventListener("keypress",this.keyPress);
         //begin :nếu sửa vào row đã đánh dấu xóa thì undelete
        tableTBody.addEventListener("change",this.tableTBodyChange);
        //end :nếu sửa vào row đã đánh dấu xóa thì undelete
        tableTBody.addEventListener("mouseover",this.tableTBodyMouseOver);
        tableTBody.addEventListener("mouseout",this.tableTBodyMouseOut);
         //End Event table
        if(iFixedColumnXPos >-1 ){
            window.addEventListener("scroll",function(e){
                autoTblObj.fixedColumn(iFixedColumnXPos,iFixedColumnIdx,isFixedColumnFromZero);
            });
        }  
        if(iFixedHeaderYPos>=0){
            window.addEventListener("scroll",function(e){
                autoTblObj.fixedHeader(iFixedHeaderYPos);
            });
        } 
        
    };
    /*---------------------------------------------------------------------------------------------------*/
    filterTable=()=>{
        let filterColumns   =  this.atbts.hasOwnProperty("filterColumns") ? this.atbts["filterColumns"] : -1;
        let inputSearch     =  this.frmControl.querySelector("input[name=search]");
        let sFilterValue    =  inputSearch.value.toUpperCase();
        let htmlTBody = this.tableData.querySelector("TBODY");
        if(filterColumns === -1){//all column
            filterColumns = [];
            for(let idx=0;idx<this.fields.length;idx++){
                let sControlType = this.fields[idx]["control_type"];
                if(!this.arrNonDataControlType.includes(sControlType)){
                    filterColumns.push(idx);
                }
            }
        }
        else if(common.isInteger(filterColumns) && filterColumns >-1){
            filterColumns = [filterColumns];//chuyển đổi number ra array
        }
        else if(!Array.isArray(filterColumns)){
            return false;
        }
        for (let i = 0; i < htmlTBody.rows.length; i++) {
            let htmlRow = htmlTBody.rows[i];
            let isMatch = false;
            for(let j = 0; j<filterColumns.length; j++){
                let idxColumn = filterColumns[j];
                if(idxColumn>=0 && idxColumn <=htmlRow.cells.length){
                    //let sValue = htmlRow.cells[idxColumn].textContent || htmlRow.cells[idxColumn].innerText;
                    let sValue =this.getContentOfCell(idxColumn,htmlRow.cells[idxColumn]);
                    if (sValue !==null && sValue.toUpperCase().indexOf(sFilterValue) > -1){
                        htmlRow.style.display = "table-row";
                        isMatch = true;
                        break;
                    }
                }
            }
            if(!isMatch){
                htmlRow.style.display = "none";
            }
        }
        this.setColSelectResizeHeight();
        return true;
    }
    /*---------------------------------------------------------------------------------------------------*/
    unSelectRow(){
        let autoTblObj = this;
        if(autoTblObj.currentCell === false){
            return;
        }
        let htmlRow = autoTblObj.currentCell.parentNode;
        if(!htmlRow){
            return; 
        }
        let controls = htmlRow.querySelectorAll("td[selected],td input[selected]")
        controls.forEach(function(control) {
            control.removeAttribute("selected");
            autoTblObj.setMultiBackgrounds(control);
        });
    }
    /*---------------------------------------------------------------------------------------------------*/
    selectRow(htmlSelectRow){
        let autoTblObj = this;
        let control = htmlSelectRow.querySelector("input:not([type=button]):not(:disabled),a");//sửa 2022-11-07
        if(control){
            control.focus();//đầu tiên là cần focus vào input hoặc a thích hợp
        }
        for(let i=0; i<htmlSelectRow.cells.length; i++){
            let htmlCell = htmlSelectRow.cells[i];
            let sType       =   autoTblObj.fields[i]["control_type"];
            let control = null;
            if(htmlCell.className === "colOrder" || sType === "checkbox" || sType === "radio" || sType === "link" || sType === "textonly" || sType === "hierarchy"){
                control = htmlCell;
            }
            else if(sType !== "button"){ 
                control = htmlCell.querySelector("INPUT");
            }
            if(control){
                control.setAttribute("selected","");
                autoTblObj.setMultiBackgrounds(control);
            }
                    
        }
       // autoTblObj.sSelectedRowKey = htmlSelectRow.getAttribute("name");
    }
    /*---------------------------------------------------------------------------------------------------*/
    unSelectColumn(){
        let autoTblObj = this;
        if(autoTblObj.currentCell === false){
            return;
        }
        let htmlTBody = autoTblObj.tableData.querySelector("TBODY");
        let htmlTHead = autoTblObj.tableData.querySelector("THEAD");
        let iSelectedColumn = autoTblObj.currentCell.cellIndex;
       // let htmlCell = htmlTHead.rows[0].cells[iSelectedColumn];
        let htmlCell = htmlTHead.querySelector(`TH[xIndex='${iSelectedColumn}']:not([colSpan]), TH[xIndex='${iSelectedColumn}'][colSpan='1']`);
        if(!htmlCell){
            return;
        }
        if(htmlCell.hasAttribute("selected")){
            htmlCell.removeAttribute("selected");
            autoTblObj.setMultiBackgrounds(htmlCell);
        }
        for(let i=0; i<htmlTBody.rows.length; i++){
            let sType =  autoTblObj.fields[iSelectedColumn]["control_type"];
            let control = null;
            if(sType === "checkbox" || sType === "radio" || sType === "link" || sType === "textonly" || sType === "hierarchy"){
                control = htmlTBody.rows[i].cells[iSelectedColumn];
            }
            else if(sType !== "button"){ 
                control = htmlTBody.rows[i].cells[iSelectedColumn].querySelector("INPUT");
            }
            if(control && control.hasAttribute("selected")){
                control.removeAttribute("selected");
                autoTblObj.setMultiBackgrounds(control);
            }
        }
        //end xóa selected column 
    }
    /*---------------------------------------------------------------------------------------------------*/
    selectColumn(iSelectedColumn){
        let autoTblObj = this;
        let htmlTBody = autoTblObj.tableData.querySelector("TBODY");
        let htmlTHead = autoTblObj.tableData.querySelector("THEAD");
        //begin. Trước tiên cần focus vài input hoặc link thích hợp
        if(htmlTBody.rows.length>0){
            let control = htmlTBody.rows[0].cells[iSelectedColumn].querySelector("input:not([type=button]):not(:disabled),a");
            if(control){
                control.focus();
            }
        }
        //End. Trước tiên cần focus vào input hoặc link thích hợp
        let htmlCell = htmlTHead.querySelector(`TH[xIndex='${iSelectedColumn}']:not([colSpan]), TH[xIndex='${iSelectedColumn}'][colSpan='1']`);
        if(htmlCell){
            htmlCell.setAttribute("selected","");
            autoTblObj.setMultiBackgrounds(htmlCell);
        }
        for(let i=0; i<htmlTBody.rows.length; i++){
            let sType =  autoTblObj.fields[iSelectedColumn]["control_type"];
            //let sField = autoTblObj.fields[i]["field"];
            let control = null;
            if(sType === "checkbox" || sType === "radio" || sType === "link" || sType === "textonly" || sType === "hierarchy"){
                control = htmlTBody.rows[i].cells[iSelectedColumn];
            }
            else if(sType !== "button"){ 
                control = htmlTBody.rows[i].cells[iSelectedColumn].querySelector("INPUT");
            }
            if(control){
                control.setAttribute("selected","");
                autoTblObj.setMultiBackgrounds(control);
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    selectRowByKey (){
        let row;
        let htmlTBody = this.tableData.querySelector("TBODY");
        if(htmlTBody.rows.length===0){    
           // row = htmlTBody.insertRow(-1); 
          //  this.initNewRow(row);   
        }
        else{
            row = htmlTBody.querySelector("tr[name='"+this.sSelectedRowKey+"']");
            if(!row){
                row = htmlTBody.rows[0];
            }
            this.focusInRow(row);
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    /*Chỉ có ý nghĩa khi có tính năng fixedUntilColumn hoặc fixedColumn. Hàm này chống cho current cell
     * (cell đang chứa input hoặc control hiện thời) không bị xén mất cạnh (thực chất là viền outline)
     * tràn ra xung quanh. Lưu ý là chỉ chống xén cạnh khi không đang ở trạng thái bình thường không kéo 
     * scroll thôi
     * 
     * @returns {undefined}
     */
    preventEdgeCurrentCellCropping(idxRightMostColumn,isFixedColumnFromZero){
        if(this.currentCell===false){
            return;
        }
        if( this.isHScroll ===false && 
            (   this.currentCell.cellIndex === idxRightMostColumn +1 || 
                (this.currentCell.cellIndex === idxRightMostColumn -1 && isFixedColumnFromZero === false)
            )
        ){
            this.currentCell.setAttribute("prevent-edge-cropping","");
        }
        else{
            this.currentCell.removeAttribute("prevent-edge-cropping");
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    //htmlInputA có thể là input,textArea,select hoặc a 
    inputAFocusIn(e,iFixedColumnIdx,isFixedColumnFromZero){
        let autoTblObj = this;
        autoTblObj.unSelectRow(); //xóa select row cũ đi
        autoTblObj.unSelectColumn(); //xóa select column cũ đi
        e = e ? e : window.event;
        let htmlInputA = e.target;
        let htmlCell = htmlInputA.parentNode;
        let idx =  htmlCell.cellIndex;
        let fields = this.fields;
        let htmlRow  = htmlCell.parentNode;
        //this.tableData.sSelectedRowKey = htmlRow.getAttribute("name");
        this.sSelectedRowKey = htmlRow.getAttribute("name");
        let newControl = false;
        let isNullable = fields[idx].hasOwnProperty("nullable") ? fields[idx]["nullable"] : false;
        if(fields[idx]["control_type"]==="link"){
            newControl = new editLink(htmlCell)
        }
        else if(fields[idx].hasOwnProperty("listData") && fields[idx]["control_type"]==="combo"){   
            let sSearchType = fields[idx].hasOwnProperty("search_type") ? fields[idx]["search_type"] : "";
            if(sSearchType === "dict_tree"){
                newControl = new treeSearchCombo(htmlInputA,fields[idx]["listData"],fields[idx]["defaultKey"],fields[idx]["treeData"],{"sFunctSelectItem":autoTblObj.changeControlValue,"nullable":isNullable});
            }
            else{
                newControl = new searchCombo(htmlInputA,fields[idx]["listData"],fields[idx]["defaultKey"],{"sFunctSelectItem":autoTblObj.changeControlValue,"nullable":isNullable});
            }
            /*Cần htmlInputA.focus() vì lý do newControl = new searchCombo sẽ làm mất focus vào htmlInputA mà thường nó chuyển focus
             * sang input arrow. Gọi ngay htmlInputA.focus() thì có khi không được mà phải dùng setTimeout để trễ một chút*/
            setTimeout(function(){ 
                if(document.activeElement!==htmlInputA){
                    htmlInputA.focus();
                } 
            },10);
            htmlCell.setAttribute("mode","ready");
        }
        else if(fields[idx]["control_type"]==="date"){
            let attr = fields[idx].hasOwnProperty("format") ? {"sShowDateFormat" : fields[idx]["format"]} :{"sShowDateFormat":"dd/mm/yyyy"};
            attr.sFunctSelectItem   = autoTblObj.changeControlValue;
            attr.nullable           = isNullable;
            newControl = new datePicker(htmlInputA,fields[idx]["defaultValue"],attr);
            setTimeout(function(){ 
                if(document.activeElement!==htmlInputA){
                    htmlInputA.focus();
                } 
            },10);
            htmlCell.setAttribute("mode","ready");
        }
        else if(fields[idx]["control_type"]==="hierarchy"){
            let objAtbts = {
                    paramDataNames:this.fields[idx]["paramDataNames"],
                    paramUrlNames:this.fields[idx]["paramUrlNames"]};
            newControl = new editHierarchy(htmlCell,this.fields[idx]["baseUrl"],null,objAtbts);
           
            newControl.htmlAFocusIn(htmlInputA);
            htmlCell.setAttribute("mode","ready");
        }
        else if(htmlInputA.tagName === "INPUT" && htmlInputA.type === "text"){ //không tính button, radio, check box
            htmlCell.setAttribute("mode","ready");
            newControl = htmlInputA;
        }
        /*else{//alink
            newControl = htmlInputA;
        }*/
        
        if(this.currentControl !== false){
            let htmlOldCell;
            if(this.currentControl.hasOwnProperty("comboClassName") && this.currentControl.comboClassName ==="search-combo"){
                htmlOldCell= this.currentControl.divCombo.parentNode;
                this.currentControl.clearCombo();
            }
            else if(this.currentControl.hasOwnProperty("datePickerClassName") && this.currentControl.datePickerClassName ==="date-picker"){
                htmlOldCell= this.currentControl.divDatePicker.parentNode;
                this.currentControl.clearDatePicker();
            }
            else if(this.currentControl.hasOwnProperty("edtHrchClassName") && this.currentControl.edtHrchClassName ==="edit-hierarchy"){
                htmlOldCell= this.currentControl.htmlContainer;
                this.currentControl.clearEditHierarchy();
            }
            else{// INPUT hoặc A LINK
                htmlOldCell= this.currentControl.parentNode;
            }
            htmlOldCell.removeAttribute("mode");
            htmlOldCell.removeAttribute("current");
            if(htmlOldCell.hasAttribute("prevent-edge-cropping")){
                htmlOldCell.removeAttribute("prevent-edge-cropping");
            }
        }
        htmlCell.setAttribute("current","");//cell đang chứa control hoặc input hoăc a Link hiện hành
        this.currentControl = newControl;
        this.currentCell = htmlCell;
        //begin chống cropping lề (phần outline) cho current cell     
        this.preventEdgeCurrentCellCropping(iFixedColumnIdx,isFixedColumnFromZero);
        //end chống cropping lề (phần outline) cho current cell
        
    };
    /*---------------------------------------------------------------------------------------------------*/
    inputDblClick (e){
        e = e ? e : window.event;
        let htmlInput = event.target;
        //let htmlCell = htmlInput.parentNode;
        let htmlCell = htmlInput.closest("TD");
        let sMode = htmlCell.getAttribute("mode");
        if(!sMode || sMode==="ready"){
            if(htmlInput.value===""){
                htmlCell.setAttribute("mode","enter");
            }
            else{
                htmlCell.setAttribute("mode","edit");
            }
        }
        else{
            htmlCell.setAttribute("mode","edit");
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    inputClick (e){ //chỉ dùng cho INPUT hoặc textara
        e = e ? e : window.event;
        let htmlInput = e.target;
        //let htmlCell = htmlInput.parentNode;
        let htmlCell = htmlInput.closest("TD");;
        let sMode = htmlCell.getAttribute("mode");
        if(sMode && sMode==="enter"){
            htmlCell.setAttribute("mode","edit");
        }
    };
    /*---------------------------------------------------------------------------------------------------*/
    focusInRow(htmlRow){
        //let htmlInput = htmlRow.querySelector("input:not([type]):not(:disabled),input[type=text]:not(:disabled),a");
        let htmlInput = htmlRow.querySelector("input:not([type=button]):not(:disabled),a");//sửa 2022-11-07
        setTimeout(function(){ 
            //phải thêm setTimeout vì trong tình huống table data vừa được tạo ra hoàn toàn bằng java script
            //trước đó thì htmlInput cũng mới được tạo ra có thể chạy không đúng lệnh input
            if(document.activeElement!==htmlInput){
                htmlInput.focus();
            } 
        },10);
    };
    /*---------------------------------------------------------------------------------------------------*/
    escKeyDownTBody=(event)=>{
        let htmlObj = event.target;
        let fields = this.fields;
        let htmlCell =  htmlObj.closest("TD");
        let idx = htmlCell.cellIndex;
        let sType     = fields[idx]["control_type"]
        let sDataType = fields[idx].hasOwnProperty("data_type") ? fields[idx]["data_type"] : "string";
        event.preventDefault();//chưa rõ có hiệu ứng ngầm gì không nhưng cứ chặn
        if(sType==="checkbox"){
            htmlObj.checked = htmlObj.oldValue;
        }
        else if(sType==="combo"){
            htmlObj.value = (htmlObj.oldValue === null ? "":htmlObj.oldValue);
        }
        else if(sType === "date"){
            let sShowDateFormat = fields[idx].hasOwnProperty("format") ? fields[idx]["format"] : "dd/mm/yyyy";
            if(htmlObj.oldValue === null){
                htmlObj.value = "";
            }
            else if(htmlObj.oldValue === false){
                htmlObj.value = "Invalid Date";
            }
            else{
                let dtValue = new Date(htmlObj.oldValue);
                htmlObj.value = dtValue.toString() === "Invalid Date" ? "Invalid Date" :string.dateToString(dtValue,sShowDateFormat);
            }
        }
        else if(sType === "textbox"){
            if(sDataType === "int" || sDataType === "number"){
                let fVal = common.numberWithCommas(htmlObj.oldValue);
                htmlObj.value = fVal === false? "Invalid number":fVal;
            }
            else{
                htmlObj.value = htmlObj.oldValue;
            }
        }
        else if(sType === "chekbox"){
            htmlObj.checked = htmlObj.oldValue;
        }
        else if(sType === "radio"){
            htmlObj.checked = htmlObj.oldValue!==null;
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    f2KeyDownTBody=(event)=>{
        let htmlObj = event.target;
        let htmlCell =  htmlObj.closest("TD");
        let sMode = htmlCell.hasAttribute("mode") ? htmlCell.getAttribute("mode") : "ready";
        event.preventDefault();//chưa rõ có hiệu ứng ngầm gì không nhưng cứ chặn
        if(sMode==="ready"){
            htmlCell.setAttribute("mode","edit");
        }
        else if(sMode==="enter"){
            htmlCell.setAttribute("mode","edit");
        }
        else if(sMode==="edit"){
            htmlCell.setAttribute("mode","enter");
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    processMoveKeys(keyCode,htmlObj){
        if(keyCode===37||keyCode===39){//left,right
            let inputs = htmlObj.closest("tr").querySelectorAll(`td:not(.edit-hierarchy)>input:not(:disabled),td:not(.edit-hierarchy)>a,td>div.search-combo>input:not([type=button]),
            td>div.date-picker>input:not([type=button]),td.edit-hierarchy>input:not([style*='display: none']),td.edit-hierarchy>a:not([style*='display: none']),td.edit-hierarchy>div>input:not([type='button'])`);
            let idx =0;
            for(idx=0;idx<inputs.length;idx++){
                if(inputs[idx] === htmlObj){
                    break;
                }
            }
            if(keyCode===37 && idx >0){
                inputs.item(idx-1).focus();
            }
            if(keyCode===39 && idx < inputs.length-1){
                inputs.item(idx+1).focus();
            }
        }
        else if(keyCode===38||keyCode===40||keyCode===13){
           // let htmlTBody = this.tableData.querySelector("TBODY");
            let row = htmlObj.closest("TR");
           // let idxRow  = row.rowIndex;
            let idxCell = htmlObj.closest("TD").cellIndex;
            let isFind = false;
            let inputA = null;
            while(!isFind && row){
                if(keyCode===38 && row.rowIndex>0){
                    row = this.tableData.rows[row.rowIndex-1];
                }
                else if((keyCode === 13 || keyCode === 40) && row.rowIndex < this.tableData.rows.length-1){
                    row = this.tableData.rows[row.rowIndex+1];
                }
                else{
                    row = null;
                }
                if(row){
                    inputA=row.cells[idxCell].querySelector("input:not(:disabled),a");
                }
                if(inputA){
                    isFind = true;
                }
            }
            if(isFind&&inputA){
                inputA.focus();
            }
        }
        
    }
    /*---------------------------------------------------------------------------------------------------*/
    moveKeyDownTBody=(event)=>{
        let htmlObj = event.target;
        let keyCode = event.which || event.keyCode;
        let htmlCell =  htmlObj.closest("TD");
        let sMode = htmlCell.hasAttribute("mode") ? htmlCell.getAttribute("mode") : "ready";
        //let tBodyObj = this.tableData.querySelector("TBODY");
        if(autoTable.isSimpleDataTagInCell(htmlObj)){
            let inputModeEdit = (htmlObj.tagName === "INPUT" && htmlObj.type === "text" && sMode === "edit");
            let enterToButtonOrA = (keyCode===13 && ((htmlObj.tagName ==="INPUT" && htmlObj.type ==="button") || htmlObj.tagName==="A"));
            if(inputModeEdit || enterToButtonOrA ){               
                return; //hai tình huống này thì không thực hiện dịch chuyển
            }
        }
        if(autoTable.isInputDestInCombo(htmlObj) || autoTable.isInputDestInDatePicker(htmlObj)){
            let inputModeEdit = (sMode === "edit");
            let moveLeftPosNotBegin  = (keyCode === 37 && htmlObj.selectionStart!==0); 
            let moveRightPosNotEnd   = (keyCode === 39 && htmlObj.selectionStart!==htmlObj.value.length);
            if(inputModeEdit || moveLeftPosNotBegin || moveRightPosNotEnd){
                return;//không thực hiện dịch chuyển
            }
        }
        //với combo thì chỉ có phím 37(left), 39(right) hoạt động. Hai phím 38 (up) và 40 (down) đã bị combo sử dụng để chọn phần tử
        //với datePicker thì 37,39 hoặt động. 38,40 hoạt động khi đang focus vào inputDest còn khi đang focus vào các button phía dưới
        //thì datePicker sử dụng để chọn ngày tháng
        this.processMoveKeys(keyCode,htmlObj);
    }
    /*---------------------------------------------------------------------------------------------------*/
    keyDownTBody=(event)=>{
        console.log("inputKeyDown của keyDownTBody");
        let htmlObj = event.target;
        let keyCode = event.which || event.keyCode;
        if(keyCode===27 && htmlObj.tagName ==="INPUT" && ["text","checkbox", "radio"].includes(htmlObj.type)){ //phím ESC
            this.escKeyDownTBody(event);
            return;
        }
        if(keyCode===113 && htmlObj.tagName ==="INPUT" && htmlObj.type === "text"){ //phím F2
            this.f2KeyDownTBody(event);
            return;
        }
        let arrMoveKey = [13,37,39,38,40];//Enter,left,right,up,down;
        if(arrMoveKey.includes(keyCode)){
            this.moveKeyDownTBody(event);
            return;
        }
    }
    /*---------------------------------------------------------------------------------------------------*/
    keyDown=(event)=>{
        event = event?event:window.event;
        let keyCode = event.which || event.keyCode;
        if(keyCode === 83 && event.ctrlKey ) {//Ctrl+S
            event.preventDefault();
            this.save();
            return; 
        }
        if(keyCode === 13 && event.target.name==="page_size"){//Enter
            event.preventDefault();// chặn hiệu ứng submit formControl
            return; 
        } 
        let tableTBody = this.tableData.querySelector("TBODY");
        let htmlObj = event.target;//htmlTag có thể là input hoặc a
        if(tableTBody.contains(htmlObj)){
            this.keyDownTBody(event);
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    /*key press chủ yếu xử lý để chuyển mode chế độ từ ready sang enter. 
     */
    keyPress=(event)=>{
        let keyCode = event.which || event.keyCode;
        let htmlObj = event.target;
        //let tableTBody = this.tableData.querySelector("TBODY");
        if(autoTable.isSimpleDataTagInCell(htmlObj) && htmlObj.tagName === "INPUT" || //phần tử INPUT nằm trong cell
           autoTable.isInputDestInCombo(htmlObj) ||  //là inputDest trong combo   
           autoTable.isInputDestInDatePicker(htmlObj)|| //là inputDest trong picker
           autoTable.isInEditHierarchy(htmlObj)
        ){
            if(keyCode === 13){//Enter  
                return;//đã xử lý ở key down rồi
            }
            let htmlCell =  htmlObj.closest("TD");
            let sMode = htmlCell.hasAttribute("mode") ? htmlCell.getAttribute("mode") : "ready";
            if(sMode === "ready"){
                //event.preventDefault();//tránh bị đúp 2 ký tự
                //htmlInput.value= String.fromCharCode(keyCode);
                //Không được dùng event.preventDefault() vì nó sẽ hủy mất sự kiện change
                htmlObj.value = "";//mặc định của keyPress sẽ tự điền nốt ký tự bàn phím gõ vào
                htmlCell.setAttribute("mode","enter");
            };
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    initNewRow(htmlRow,iFixedColumnIdx,isFixedColumnFromZero){
        let fields    = this.fields;
        let idxAddRow  = this.idxAddRow;
        if(idxAddRow >= this.arrAddRowId.length){
            return;
        }
        let sNewTmpId = this.arrAddRowId[idxAddRow];
        htmlRow.setAttribute("name",sNewTmpId);
        htmlRow.setAttribute("new","");
        //let iRowHeight = common.innerHeight(htmlRow);
        for(let i=0;i<fields.length;i++){
            let sType = fields[i]["control_type"];
            let sDataType = fields[i].hasOwnProperty("data_type") ? fields[i]["data_type"] : "string";
            let iMaxLength = fields[i].hasOwnProperty("maxlength") ? fields[i]["maxlength"] : -1;
            let htmlTD = htmlRow.insertCell(-1);
            if(i===0){
                htmlTD.className = "colOrder";
            }
            if(iFixedColumnIdx>-1 && ((isFixedColumnFromZero && i<=iFixedColumnIdx)||(!isFixedColumnFromZero && i===iFixedColumnIdx))){
                htmlTD.setAttribute("fixed",i);
            }
            let control = null;
            if(sType==="link"){
                control = document.createElement("A");
            }
            else if(sType !=="textonly"){
                control = document.createElement("INPUT");
                if(sType ==="combo" || sType ==="date" || sType ==="textbox" ){
                    if(iMaxLength>0){
                        control.setAttribute("maxlength",iMaxLength);
                    }
                }
            }
            if(sType === "button"){
                control.type = sType;
                control.className = fields[i]["class"];
                if(autoTable.arrClassControlButton.filter( //bổ sung 2023-02-28
                    (item) => control.classList.contains(item.toString())).length
                ){
                    htmlTD.className = "colControl";
                }
            }
            else if(sType === "checkbox"){
                control.type = sType;
                //control.checked = false;
                control.checked = fields[i].hasOwnProperty("defaultValue") ? fields[i]["defaultValue"]:false;
            }
            else if(sType === "radio"){
                control.type = sType;
                control.name = fields[i]["field"] + "_" + htmlRow.getAttribute("name");
                control.value = fields[i]["selectedKey"];
                if(fields[i].hasOwnProperty("defaultKey")&& fields[i]["selectedKey"] == fields[i]["defaultKey"]){
                    control.checked = true;
                }
                //không dùng === được vì đôi khi sẽ có cả tình huống value = 1, defaultKey = "1"
            }
            else if(sType === "combo"){
                control.setAttribute("type",sType);//thêm cái này để chống căn lề phải khi data_type = int or number
                control.setAttribute("data_type",sDataType);
                control.value = fields[i].hasOwnProperty("defaultValue") && fields[i]["defaultValue"]!== null ? fields[i]["defaultValue"] : "";
                //control.style.width = String(fields[i]["col_width"]) + "px";
            }
            else if(sType === "date"){
                control.setAttribute("data_type",sDataType);
                control.value = fields[i].hasOwnProperty("defaultValue") && fields[i]["defaultValue"]!== null ? fields[i]["defaultValue"] : "";
               // control.style.width = String(fields[i]["col_width"]) + "px";
            }
            else if(sType === "textbox"){
                control.setAttribute("data_type",sDataType);
                let val = "";
                if(sDataType === "int" || sDataType === "number"){
                   val = 0;
                }
                if(!fields[i].hasOwnProperty("defaultValue")){
                    control.value = val;
                }
                else if(fields[i]["defaultValue"] === null){
                    control.value = "";
                }
                else{
                    control.value = fields[i]["defaultValue"];
                } 
            }
            else if(sType === "link"){
                control.innerHTML = "Số liệu mới";
                control.href = "";
                control.target = "blank"
            }
            else if(sType === "textonly"){
                htmlTD.innerHTML = "";
            }
            if(control!==null){
                control.setAttribute("new","");
                htmlTD.appendChild(control);
            }
        }//end for
        this.setColumnOrder(htmlRow.rowIndex);
    };
    /*----------------------------------------------------------------------------------------------------*/
    addRow(rowCurrent,iFixedColumnIdx,isFixedColumnFromZero){
        let idxAddRow  = this.idxAddRow;
        if(idxAddRow >= this.arrAddRowId.length){
            return;
        }
        let rowNew;
        if(rowCurrent){
            rowNew  = this.tableData.insertRow(rowCurrent.rowIndex+1);
        }
        else{//vị trí đầi tiêm
            rowNew = this.tableData.querySelector("tbody").insertRow(0); 
        }
        this.initNewRow(rowNew,iFixedColumnIdx,isFixedColumnFromZero);
        this.idxAddRow = this.idxAddRow+1;
        this.focusInRow(rowNew);
        this.setColSelectResizeHeight();//2023-11-09
    };  
    /*----------------------------------------------------------------------------------------------------*/
    delRow(rowDel){
        let autoTblObj = this;
        if(!rowDel.hasAttribute("new")){// row cũ   
            if(!rowDel.hasAttribute("delete")){// delete
                rowDel.setAttribute("delete","");
                for(let i=0;i<rowDel.cells.length;i++){
                    let sType =  autoTblObj.fields[i]["control_type"];
                    let sField = autoTblObj.fields[i]["field"];
                    let control =  null;
                    if(sField === "order"){
                        control = null;
                    }else if(sType === "checkbox" || sType === "radio" || sType === "link" || sType === "textonly" || sType === "hierarchy"){
                        control = rowDel.cells[i];
                    }
                    else if(sType !== "button"){ 
                        control = rowDel.cells[i].querySelector("INPUT");
                    }
                    if(control){
                        control.setAttribute("delete","");
                        autoTblObj.setMultiBackgrounds(control);
                    }
                }
                
            }
            else{ //undelete
                rowDel.removeAttribute("delete"); 
                let inputs =rowDel.querySelectorAll("td[delete], td input[delete]");
                inputs.forEach(e=>{
                    e.removeAttribute("delete");
                    autoTblObj.setMultiBackgrounds(e);
                });
            }    
        }
        else{//row mới
            let rowNext = this.tableData.rows[rowDel.rowIndex+1];
            rowDel.parentNode.removeChild(rowDel);
            if(rowNext){
                this.focusInRow(rowNext);
                this.setColumnOrder(rowNext.rowIndex);
            }
            this.setColSelectResizeHeight();//2023-11-09
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    delAll(){
        let htmlTBody = this.tableData.querySelector("TBODY");
        let newRows =  htmlTBody.querySelectorAll("tr[new]");
        //Xóa hết các row mới tạo
        newRows.forEach(e=>htmlTBody.removeChild(e));
        this.setColSelectResizeHeight();
        let delRows =  htmlTBody.querySelectorAll("tr[delete]");
        if(delRows.length === htmlTBody.rows.length){//undelete all
            delRows.forEach(e=>this.delRow(e));
        }
        else{//delete nốt các phần tử còn lại
            let notDelRows =  htmlTBody.querySelectorAll("tr:not([delete])");
            notDelRows.forEach(e=>this.delRow(e));
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    /* Khi một html object đươc set multi background image thì không thể dùng cách set class hay attribute
     * thông thường vài image background của class sau hoặc attribute sau sẽ đè lên cái trước. Chỉ có thể
     * set thông qua style
     * 
     * @param {type} htmlObj
     * @returns {Generator}
     */
    setMultiBackgrounds(htmlObj){
        //getComputedStyle
        let arrClassBackground = []; //chứa các class của htmlObj có background color hoặc background image
        let objAttBackground = {}; //chứa các attribute của htmlObj có background color hoặc background image
        let objBackground = {"backgroundColor":[],"backgroundImage":[],"backgroundSize":[],"backgroundPosition":[],"backgroundRepeat":[]};
        //begin. Nạp các giá trị cho arrClassBackground và objAttBackground
        autoTable.arrClassBackground.forEach(function(classBackground){
            if(htmlObj.classList.contains(classBackground)){
                arrClassBackground.push(classBackground);
            }
        })
        autoTable.arrAttBackground.forEach(function(attBackground){
            if(htmlObj.hasAttribute(attBackground)){
                objAttBackground[attBackground] = htmlObj.getAttribute(attBackground);
            }
        })
        //end. Nạp các giá trị cho arrClassBackground và objAttBackground
        // begin xóa hết các class và attribute có liên quan đến background của htmlObj
        if(arrClassBackground.length>0){
            htmlObj.classList.remove(arrClassBackground.toString());
        }
        if(!common.isEmpty(objAttBackground)){
            for(let prop in objAttBackground){
                htmlObj.removeAttribute(prop);
            }
        } 
        // end xóa hết các class và attribute có liên quan đến background của htmlObj
        htmlObj.style.background = ""; // xóa style background
        //Begin nạp từng attr background vào htmlObj để lấy ra các giá trị backgroundImage,backgroundSize,backgroundPosition,backgroundRepeat
        for(let prop in objAttBackground){
            htmlObj.setAttribute(prop,objAttBackground[prop]);
            let styles = window.getComputedStyle(htmlObj);
            if(styles.backgroundColor!=="rgb(255, 255, 255)"){ 
                objBackground.backgroundColor.push(styles.backgroundColor);
            }
            objBackground.backgroundImage.push(styles.backgroundImage);
            objBackground.backgroundSize.push(styles.backgroundSize);
            objBackground.backgroundPosition.push(styles.backgroundPosition);
            objBackground.backgroundRepeat.push(styles.backgroundRepeat);
            htmlObj.removeAttribute(prop);
        }    
        //end nạp từng attr background vào htmlObj để lấy ra các giá trị backgroundImage,backgroundSize,backgroundPosition,backgroundRepeat
        //Begin nạp từng class background vào htmlObj để lấy ra các giá trị backgroundImage,backgroundSize,backgroundPosition,backgroundRepeat
        for(let i=0;i<arrClassBackground.length;i++){
            htmlObj.classList.add(arrClassBackground[i]);
            let styles = window.getComputedStyle(htmlObj);
            if(styles.backgroundColor!=="rgb(255, 255, 255)"){
                objBackground.backgroundColor.push(styles.backgroundColor);
            }
            objBackground.backgroundImage.push(styles.backgroundImage);
            objBackground.backgroundSize.push(styles.backgroundSize);
            objBackground.backgroundPosition.push(styles.backgroundPosition);
            objBackground.backgroundRepeat.push(styles.backgroundRepeat);
            htmlObj.classList.remove(arrClassBackground[i]);
        }
        //end nạp từng class background vào htmlObj để lấy ra các giá trị backgroundImage,backgroundSize,backgroundPosition,backgroundRepeat
        //Begin: hiệu chỉnh lại backgroundPosition chứa cấc thành phần như 0%, 50%, 100% vì khi set multibackground nó không hoạt động
        for(let i=0;i<objBackground.backgroundPosition.length;i++){
            let stringArray = objBackground.backgroundPosition[i].trim().split(/\s+/);
            for(let j=0; j<stringArray.length; j++){
                let sPos = stringArray[j];
                if(j === 0){
                    if(sPos === "0%"){
                        stringArray[j] = "left";
                    }
                    else if(sPos === "50%"){
                        stringArray[j]  = "center";
                    }
                    else if(sPos === "100%"){
                        stringArray[j]  = "right";
                    }
                }
                else if(j === 1 || j === 2){
                    if(sPos === "0%"){
                        stringArray[j] = "top";
                    }
                    else if(sPos === "50%"){
                        stringArray[j]  = "center";
                    }
                    else if(sPos === "100%"){
                        stringArray[j]  = "bottom";
                    }
                }
           }
           objBackground.backgroundPosition[i] = stringArray.join(" "); 
        }
        //end: hiệu chỉnh lại backgroundPosition chứa cấc thành phần như 0%, 50%, 100% vì khi set multibackground nó không hoạt động
        //Begin Set multibackground thông qua style
        
        if(objBackground.backgroundColor.length>0){
            //htmlObj.style.backgroundColor       = objBackground.backgroundColor[0];// backgroundColor không set multi được, chỉ set 1 giá trị duy nhất
            //nếu có nhiều backgroundColor thì dùng màu trung bình cộng 
            let arrRGB = [0,0,0];
            let iCount = 0;
            for(let i= 0; i<objBackground.backgroundColor.length;i++){
                let arrTmp = common.getRGB(objBackground.backgroundColor[i]);
                if(arrTmp!==null){
                    arrRGB[0] = arrRGB[0] + arrTmp[0];
                    arrRGB[1] = arrRGB[1] + arrTmp[1];
                    arrRGB[2] = arrRGB[2] + arrTmp[2];
                    iCount++;
                }
            }
            htmlObj.style.backgroundColor  = `rgb(${Math.round(arrRGB[0]/iCount)}, ${Math.round(arrRGB[1]/iCount)}, ${Math.round(arrRGB[2]/iCount)})`;
        }
        htmlObj.style.backgroundImage       = objBackground.backgroundImage.toString();
        htmlObj.style.backgroundSize        = objBackground.backgroundSize.toString();
        htmlObj.style.backgroundPosition    = objBackground.backgroundPosition.toString();
        htmlObj.style.backgroundRepeat      = objBackground.backgroundRepeat.toString();
        //End Set multibackground thông qua style
        
        //Begin. Set lại các class, attr liên quan đến background cho htmlObj
        if(arrClassBackground.length>0){
            htmlObj.classList.add(arrClassBackground.toString());
        }
        if(!common.isEmpty(objAttBackground)){
            for(let prop in objAttBackground){
                htmlObj.setAttribute(prop,objAttBackground[prop]);
            }
        }
        //End. Set lại các class, attr liên quan đến background cho htmlObj
    }
    /*----------------------------------------------------------------------------------------------------*/
    /*
     * Trước khi chạy hàm showError thì hệ thống luôn chạy trước hàm isValidate để xóa sach các lỗi và
     * thông báo lỗi cũ
     * @param {type} objErr có cấu trúc 
     * {"status":sStatus ,"info":[0:objDetail,1:objBrief],"extra":{"delete":iNumDelete,"update":iNumUpdate,"add":iNumAdd}}
     */
    showError(objErr){
        let autoTblObj = this;
        let frmControl   = this.frmControl;
        let divMessage   =  frmControl.querySelector("div.message"); 
        /*ERR_STATUS.client_error,ERR_STATUS.server_error,ERR_STATUS.server_logic_error thì các row dữ liệu upload lên đều đồng thời ở trạng thái đó
         * ERR_STATUS.server_ok thì tất cả các row dữ liệu up lên đều ở trạng thái OK
         * ERR_STATUS.server_incomplete thì sẽ có ít nhất 1 row dữ liệu là ERR_STATUS.server_ok, còn lại lẫn lộn vào 1 hay nhiểu row là ERR_STATUS.server_error,ERR_STATUS.server_logic_error
         */
        if(objErr.status===ERR_STATUS.client_error|| objErr.status===ERR_STATUS.server_error || objErr.status===ERR_STATUS.server_logic_error){
            divMessage.setAttribute("error","");
        }
        else{
            divMessage.removeAttribute("error");
        } 
        if(objErr.hasOwnProperty("extra") && objErr.extra !==""){
            let sMsg = "";
            if(typeof(objErr.extra)==="object"){
                for(let sAction in objErr.extra){
                    switch(sAction) {
                        case "delete":
                        sMsg = sMsg + "<p>Xóa thành công: " + objErr["extra"][sAction]+" phần tử.<p>";
                        break;
                        case "update":
                        sMsg = sMsg + "<p>Thay đổi thành công: " + objErr["extra"][sAction]+" phần tử.</p>";
                        break;
                        case "add":
                        sMsg = sMsg + "<p>Thêm mới thành công: " + objErr["extra"][sAction]+" phần tử.</p>";
                        break;
                    }
                }
            }
            else{
                sMsg = "<p>" + objErr.extra + "</p>";
            }
            divMessage.innerHTML = sMsg;
        }
        if( objErr.info[0] === undefined || //không có objErr.info[0]
            (Array.isArray(objErr.info[0]) && objErr.info[0].length===0) || // array is emmpty
            common.isEmpty(objErr.info[0]) //object is empty
        ){ 
            return objErr.status;
        }
        let extAErrDetail = new ExtArray();
        extAErrDetail.data = objErr.info[0];
        let fields = this.fields;
        let htmlTBody = this.tableData.querySelector("TBODY");
        extAErrDetail.processLeaf = function(arrChain,sDescription){
            let sRowId      = arrChain[0];
            let sFieldName  = arrChain[1];
            let sErrCode     = arrChain[2];
            let sErrSubCode  = arrChain[3];
            let htmlRow     = htmlTBody.querySelector("tr[name='"+sRowId+"']");
            if(!htmlRow){
                return;
            }
            let isErrAllRow = false;
            if(sFieldName==="*"){//lỗi toàn row
                isErrAllRow = true;
            }
            else{//lỗi field
                isErrAllRow = false;
            }
            for(let i=0;i<htmlRow.cells.length;i++){
                let htmlInput=false;
                if(isErrAllRow){
                    htmlInput = htmlRow.cells[i].querySelector("INPUT");
                }
                else if(fields[i]["field"]=== sFieldName){//đúng cột có lỗi
                    htmlInput = htmlRow.cells[i].querySelector("INPUT");
                }
                if(htmlInput && autoTblObj.fields[i]["control_type"]!=="button"){
                    sErrSubCode = sErrSubCode === true || sErrSubCode === false ? "" : sErrSubCode; //2025-02-22
                    let sAttrErrCode = sErrSubCode === ""  ? "err--" + sErrCode : "err--" + sErrCode + "--" + sErrSubCode;
                    sAttrErrCode = sAttrErrCode.replaceAll("_", "-"); // Thay _ thành - vì trong CSS dùng dấu - và --
                    htmlInput.setAttribute("err",sAttrErrCode);
                    autoTblObj.setMultiBackgrounds(htmlInput);
                }
            }
        };
        extAErrDetail.browseTree(extAErrDetail.data,[]);
        if( objErr.info[1] === undefined || //không có objErr.info[1]
            (Array.isArray(objErr.info[1]) && objErr.info[1].length===0) || // array is emmpty
            common.isEmpty(objErr.info[1]) //object is empty
        ){ 
            return objErr.status;
        }
        //có mô tả lỗi kiểu ngắn gọn
        let extAErrBrief = new ExtArray();
        extAErrBrief.data = objErr.info[1];
        //let sSummaryErr = divMessage.innerHTML;
        let sSummaryErr = "";
        extAErrBrief.filterBranch = function(arrChain,sDescription){
            if(arrChain.length<2){
                return EXT_ARRAY.branch;// branch
            }
            else{
                return EXT_ARRAY.leaf;//khi = 2 thì đạt tới nút lá
            }
        }
        extAErrBrief.processLeaf = function(arrChain,objDescription){
            let sErrCode     = arrChain[0];
            let sErrSubCode  = arrChain[1];
            let sTxt ="";
            if(objDescription===null){
                return;//không hiển thị lỗi
            }
            else if(objDescription===""){//lấy từ hệ thống báo lỗi chuẩn
                sTxt = ERR_DATA.getErrDescription(sErrCode,sErrSubCode);
            }
            else if(objDescription.hasOwnProperty("names")){//lỗi logic 
                sTxt =  objDescription["names"].join("");
                if(sTxt !== ""){//sTxt ==="" khi các phần tử của names đều = "", đó là các lỗi run_sql chuyển sang thành lỗi logic và không xác định được lỗi cụ thể
                    sTxt =  objDescription["names"].join(", ");
                }
                if(objDescription["message"] === ""){
                    sTxt = `${ERR_DATA.getErrDescription(sErrCode,sErrSubCode)} ${objDescription["names"].length} phần tử ${sTxt}`;
                }
                else{
                    sTxt = `${ERR_DATA.getErrDescription(sErrCode,sErrSubCode)} ${objDescription["names"].length} phần tử ${sTxt}: ${objDescription["message"]}`;
                }
            }
            else if(Array.isArray(objDescription) && sErrCode === "run_sql"){
                sTxt = `${objDescription.length} phần tử bị lỗi khi chạy lệnh sql:<br>`;
                for (let i =0; i<objDescription.length;i++){
                    sTxt =  `${sTxt} ${i+1}.  ${objDescription[i]} <br>`;
                }
            }
            //sSummaryErr = sSummaryErr + "<p><img alt=\"\" src='"+ERR_DATA.getErrImage(errCode,errSubCode)+"'>"+objDescription+"</p>"
            let sAttrErrCode = sErrSubCode==="" ? "err--" + sErrCode : "err--" + sErrCode + "--" + sErrSubCode;
            sAttrErrCode = sAttrErrCode.replaceAll("_", "-"); // Thay _ thành - vì trong CSS dùng dấu - và --
            sSummaryErr = sSummaryErr + "<p><img alt=\"\" err=\""+sAttrErrCode+"\">"+sTxt+"</p>";
        };
        extAErrBrief.browseTree(extAErrBrief.data,[]);
        if(sSummaryErr!==""){
            divMessage.innerHTML = divMessage.innerHTML+sSummaryErr;
        }
        return objErr.status;
    };
    /*----------------------------------------------------------------------------------------------------*/
    isChangedCellValue(htmlCell,iIndex,sControlType){
        let input       = htmlCell.querySelector("INPUT");
        let currentValue = this.getKeyOrValue(iIndex,htmlCell);
        if(
            (sControlType === "checkbox" && input.oldValue !=currentValue)||
            (sControlType === "radio" && input.checked && input.oldValue !=currentValue) ||
            (sControlType === "combo" && input.oldKey !=currentValue) ||
            (sControlType === "date" && input.oldValue !=currentValue) ||
            (sControlType === "textbox" && input.oldValue !=currentValue)
        ){
            return true;
        }
        return false;
    }
    /*----------------------------------------------------------------------------------------------------*/
    isChanged(){
        let userRight = this.atbts.hasOwnProperty("userRight") ? this.atbts["userRight"] : 0;
        if( !(userRight & USER_RIGHT["update_right"]) && 
            !(userRight & USER_RIGHT["add_right"])    && 
            !(userRight & USER_RIGHT["delete_right"])){
            return false;
        }
        let fields = this.fields;
        let htmlTBody = this.tableData.querySelector("TBODY");
        if(!htmlTBody){
            return false;//chưa có dữ liệu, chưa có gì thay đổi
        }
        for(let i=0;i<htmlTBody.rows.length;i++){
            let row = htmlTBody.rows[i];
            if(row.style.display === "none"){
                continue;//2022-07-19. không xét các row đang bị hide do ảnh hưởng của filter
            }
            if(row.hasAttribute("delete")||row.hasAttribute("new")){
                return true;
            }
            for(let j=0;j<row.cells.length;j++){
               // let input       =row.cells[j].querySelector("INPUT");
                let sControlType       =fields[j]["control_type"];
                if(this.isChangedCellValue(row.cells[j],j,sControlType)){
                    return true;
                }
            }//end for j
        }//end for i
        return false;//không có thay đổi gì
        
    };
    /*----------------------------------------------------------------------------------------------------*/
    /*row đã xóa thì không bắt lỗi*/
    validateDeletedRow(row){
        for(let j=0;j<row.cells.length;j++){
            let input = row.cells[j].querySelector("INPUT");
            if(input){
                input.removeAttribute("err");
                this.setMultiBackgrounds(input);
            }
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    validateNomalRow(row,dTreeError,dTreeErrorBrief){
        let fields = this.fields;
        let sRowId = row.getAttribute("name");
         //xóa bỏ các lỗi cũ và gom chọn các lỗi mới cho hàm showError    
        for(let j=0;j<row.cells.length;j++){
            let sType       =   fields[j]["control_type"];
            let sDataType   =   fields[j].hasOwnProperty("data_type") ? fields[j]["data_type"] : "string";
            let fieldName   =   fields[j]["field"];
            //chỉ kiểm tra dữ liệu với các loại input này, không kiểm tra với radio, button, checkbox
            //if(sType==="textbox" || sType==="combo" || sType==="date"){
            if(!this.arrMustCheckControlDataType.includes(sType)){
                continue; //không phải các loại control type có thể gây lỗi không kiểm tra nữa
            }
            let input = row.cells[j].querySelector("INPUT");
            input.removeAttribute("err");//xóa bỏ các lỗi cũ
            this.setMultiBackgrounds(input);
            //input.value = string.trim(input.value);  
            //let currentValue = this.getKeyOrValue(j,input);
            let currentValue = this.getKeyOrValue(j,row.cells[j]);
            if(currentValue===null){//control.value === "" và được phép có giá trị blank
                continue; //không kiểm tra gì nữa
            }
            //begin check lỗi kiểu dữ liệu trước   
            if(input.value !== "" && currentValue===false){
                //currentValue === false mean: giá trị hiện thời sai,input.value !=="" mean vì chỉ xét currentValue khi input.value !==""
                if(sType==="combo"){//2024-11-23
                    dTreeError.setObjectValue([sRowId,fieldName,"date",""],"","unique_array");
                    dTreeErrorBrief.setObjectValue(["date",""],"","replace");
                }
                else if(sType==="date"){
                    dTreeError.setObjectValue([sRowId,fieldName,"date",""],"","unique_array");
                    dTreeErrorBrief.setObjectValue(["date",""],"","replace");
                }
                //chỉ check lỗi kiểu nếu giá trị khác empty, nếu empty thì check lỗi not_blank ỏ phần sau
                else if(sType==="textbox"){
                    if(sDataType === "int" || sDataType === "number" || sDataType === "email" || sDataType === "url"){
                        dTreeError.setObjectValue([sRowId,fieldName,sDataType,""],"","unique_array");
                        dTreeErrorBrief.setObjectValue([sDataType,""],"","replace");
                    }
                }
                continue;//2022-01-06. Hễ có lỗi về kiểu thì thôi không check lỗi contrains nữa. Để đảm bảo một field tại một thời điểm chỉ có 1 lỗi
            }
            //end check lỗi kiểu dữ liệu trước   
            //begin check lỗi constraint
            if(!fields[j].hasOwnProperty("constraints")){
                continue;//không có ràng buộc constraints, thôi không kiểm tra nữa
            }
            //begin kiểm tra lỗi constraints
            let constraints = fields[j]["constraints"];
            let sInfo;//các lỗi như required,must_be_in_list thì không có mã lỗi phụ, sInfo = "" 
            //ta sử dụng cấu trúc if/else vì 1 field có thể có nhiều lỗi nhưng hễ gặp 1 lỗi thì báo lỗi trên field đó và không càn xét các error khác nữa
            if(constraints.hasOwnProperty("required") && input.value===""){
               sInfo = constraints["required"];  
               dTreeError.setObjectValue([sRowId,fieldName,"required",sInfo],"","unique_array");  
               dTreeErrorBrief.setObjectValue(["required",sInfo],"","replace");
            }
            //currentValue ở đây là key
            //else if(constraints.hasOwnProperty("must_be_in_list") && constraints["must_be_in_list"] && currentValue===""){ 
            else if(constraints.hasOwnProperty("must_be_in_list") && constraints["must_be_in_list"] && currentValue===false){//sửa 2024-11-24  
                sInfo = constraints["must_be_in_list"];  
                dTreeError.setObjectValue([sRowId,fieldName,"must_be_in_list",sInfo],"","unique_array"); 
                dTreeErrorBrief.setObjectValue(["must_be_in_list",sInfo],"","replace");
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
                            dTreeError.setObjectValue([sRowId,fieldName,sOperator,sInfo],"","unique_array");    
                            dTreeErrorBrief.setObjectValue([sOperator,sInfo],"","replace");
                            break;//2022-01-18 hễ có 1 lỗi thì thôi không cần for tiếp nữa
                        }     
                    }
                }
            }
            //end check lỗi constraint
            //end for j
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    isValidate(){
        let divMessage   =  this.frmControl.querySelector("div.message"); 
        divMessage.innerHTML="";//xóa hết các thông báo lỗi cũ
        let dTreeError = new ExtArray();//mô tả chi tiết, mô tả theo row_id, field_id, mã lỗi chính, mã lỗi phụ
        let dTreeErrorBrief = new ExtArray();//mô tả đơn giản hơn chỉ liệt kê theo mã lỗi và mã lỗi phụ
        let fields = this.fields;
        let htmlTBody = this.tableData.querySelector("TBODY");
        let htmlDeletedRow = null; //name của row kế tiếp delete row
        for(let i=0;i<htmlTBody.rows.length;i++){
            let row = htmlTBody.rows[i];
            if(row.style.display === "none"){
                continue;//2022-07-19. không xét các row đang bị hide do ảnh hưởng của filter
            }
            
            if(row.hasAttribute("delete")){//xóa lỗi không được delete đi nếu có
                htmlDeletedRow = row;
                this.validateDeletedRow(row);
                continue;
            }
             //xóa bỏ các lỗi cũ và gom chọn các lỗi mới cho hàm showError    
            this.validateNomalRow(row,dTreeError,dTreeErrorBrief);
        } 
        //Begin đoạn code này để nếu có xóa row dữ liệu thì cung cấp lên máy chủ ID của row kế tiếp để cho sau khi save thì focus vào row đó
        let sRowNameAfterDeletedRow = "";
        if(htmlDeletedRow){
            //xóa 1 row đi thì row kế tiếp sẽ có rowIndex = index của row đã xóa. THuật toán này hiện cũng không đúng lắm
            let nextRow = htmlDeletedRow.parentNode.parentNode.rows[htmlDeletedRow.rowIndex+1];
            if(nextRow){
                sRowNameAfterDeletedRow = nextRow.getAttribute("name");
            }
        }
        //End đoạn code này để nếu có xóa row dữ liệu thì cung cấp lên máy chủ ID của row kế tiếp để cho sau khi save thì focus vào row đó
        return [dTreeError.data,dTreeErrorBrief.data,sRowNameAfterDeletedRow];
        
    };
    /*----------------------------------------------------------------------------------------------------*/
    confirmExitTable(){
        if(this.isChanged()){
            return confirm("Một số dữ liệu đã thay đổi. Bạn muốn rời đi mà không lưu lại sự thay đổi này?");
        }
        return true;
    };
    /*----------------------------------------------------------------------------------------------------*/
    getNewDataByRow(newRow){
        let newElement = {};
        let fields = this.fields;
        for(let j=0;j<newRow.cells.length;j++){
            let input       =newRow.cells[j].querySelector("INPUT");
            let sType       =fields[j]["control_type"];
            let sFieldName  =fields[j]["field"];
            if( this.arrEditableControlDataType.includes(sType) && 
                (sType !== "radio" || (sType === "radio" && input.checked)) ){//loại trừ các button control
                    //let currentValue = this.getKeyOrValue(j,input);
                    let currentValue = this.getKeyOrValue(j,newRow.cells[j]);
                    /*if(currentValue!==null){ 
                        //2022-07-28 để fix lỗi khi add nhiều row sinh ra tình trạng có row có số cột không đồng nhất
                       newElement[sFieldName] = currentValue;
                    }*/
                    newElement[sFieldName] = currentValue;
            }
        }
        newElement["_virtual_key"] = newRow.getAttribute("name");
        return newElement;
    };
    /*----------------------------------------------------------------------------------------------------*/
    getUpdateDataByRow(updatedRow){
        let fields = this.fields;
        let update =  {};
        for(let j=0;j<updatedRow.cells.length;j++){
            let input       =updatedRow.cells[j].querySelector("INPUT");
            let sType       =fields[j]["control_type"];
            let sFieldName  =fields[j]["field"];
            if(this.arrEditableControlDataType.includes(sType) && 
                (sType !== "radio" || (sType === "radio" && input.checked))){
                //let currentValue = this.getKeyOrValue(j,input);
                let currentValue = this.getKeyOrValue(j,updatedRow.cells[j]);
                if(sType==="combo"){
                    if(input.oldKey != currentValue){
                        update[sFieldName]= currentValue;  
                    }
                }
                else if(input.oldValue != currentValue){
                    update[sFieldName]= currentValue;
                } 
            }
        }
        //thêm filed _key có dầu _ vì để khỏi trùng tên trường
        if(!common.isEmpty(update)){
            update["_key"] = this.createObjectKeyOfRow(updatedRow);
        }
        return update;
    };
    /*----------------------------------------------------------------------------------------------------*/
    createObjectKeyOfRow(htmlRow){
        let arrValue = htmlRow.getAttribute("name").split(LIST_SEPARATOR_CHAR);
        let objResult = {};
        let idx = 0;
        for(let fieldName of this.keyFields){
            objResult[fieldName] = arrValue[idx];
            idx++;
        }
        return objResult;
    }
    /*----------------------------------------------------------------------------------------------------*/
    save(){
        let frmControl = this.frmControl;
         //Begin chặn không submit nữa khi đang submit
        let inputLoading = frmControl.querySelector("input.loading"); 
        if(inputLoading && inputLoading.style.display === "block"){
           return;
        }
        //End chặn không submit nữa khi đang submit
        let errInfo = this.isValidate();
        if(!common.isEmpty(errInfo[0])){ // khóa tạm 
            let err = {"status":ERR_STATUS.client_error,"info":errInfo,"extra":""};
            this.showError(err);
            return;
        }
        if(this.tableData.querySelectorAll("tr[delete]").length){    
            if(confirm("Một số dữ liệu sẽ bị xóa. Bạn thực sự muốn làm điều này?")===false){
                return;
            }   
        }
        //let fieldName;
        let fields = this.tableData.fields;
        let htmlTBody = this.tableData.querySelector("TBODY");
        let extAPost = new ExtArray();
        let idxDel = 0;
        let idxAdd = 0;
        let idxUpdate = 0;
        for(let i=0;i<htmlTBody.rows.length;i++){
            let row = htmlTBody.rows[i];
            if(row.style.display === "none"){
                continue;//2022-07-19. không xét các row đang bị hide do ảnh hưởng của filter
            }
            if(row.hasAttribute("new")){//add new
                let newElement = this.getNewDataByRow(row,fields);
                extAPost.setObjectValue(["add",idxAdd++],newElement,"unique_array");
            }
            else if(row.hasAttribute("delete")){    
                let objRowId = this.createObjectKeyOfRow(row);
                extAPost.setObjectValue(["delete",idxDel++],objRowId,"replace");
            }
            else{
                let updateElement = this.getUpdateDataByRow(row);
                if(!common.isEmpty(updateElement)){
                    extAPost.setObjectValue(["update",idxUpdate++],updateElement,"replace");
                }
            }
        }
        let postData = extAPost.data;
        if(!common.isEmpty(postData)){
            postData["return_row_id"] =  errInfo[2]; // postData["return_row_id"] chỉ khác "" khi có delete row
            this.postData(postData);
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    /*
     * Mục đích là để giả lập event change khi ta thay đổi giá trị của các loại control như là 
     * smallAutoSearhCombo hoặc datePicker bằng code.
     * @param {type} control
     * @returns {undefined}
     */
    changeControlValue(control){
        let event = new Event('change',{"bubbles":true});
        control.dispatchEvent(event); //gây kích hoạt handler: tableTBody.addEventListener("change"
       // control.focus();//2024-05-09 bỏ đi ngày2024-07-08 vì không phù hợp với datePicker
    }
    /*----------------------------------------------------------------------------------------------------*/
    fixedHeader(iFixedHeaderYPos){
        let frmControl = this.frmControl;
        //Đề phòng event firing khi các dữ liệu chưa khởi tạo xong
        if(!frmControl ||iFixedHeaderYPos <0){
            return;
        }
        let htmlTHs = this.tableData.querySelectorAll("thead th");
        //let iScrollTop = window.pageYOffset || document.body.scrollTop;
        let iScrollTop = common.getScroolTop();
        if(iScrollTop>iFixedHeaderYPos){
            frmControl.style.top = String(iScrollTop - iFixedHeaderYPos) +"px";
            for (let i=0; i < htmlTHs.length; i++) {
                htmlTHs[i].style.top = String(iScrollTop-iFixedHeaderYPos) + "px";
            }
        }else{
            frmControl.style.top = "0px";
            for (let i=0; i < htmlTHs.length; i++) {
                htmlTHs[i].style.top =  "0px";
            }
        }
    };
    /*----------------------------------------------------------------------------------------------------*/
    //fixedColumn(iRightMostColIdx,isFixedColumnFromZero){
    fixedColumn(iFixedColumnXPos,iRightMostColIdx,isFixedColumnFromZero){    
        //Đề phòng event firing khi các dữ liệu chưa khởi tạo xong
        if(!this.frmControl || !this.tableData || iFixedColumnXPos <0){
            return;
        }
        let frmControl = this.frmControl;
        let tableData = this.tableData;
        let htmlCells = tableData.querySelectorAll("th[fixed],td[fixed]");
       // let iScrollLeft = window.pageXOffset || document.body.scrollLeft;
        let iScrollLeft = common.getScroolLeft();
        if(iScrollLeft > iFixedColumnXPos){
            this.isHScroll = true; //đang kéo scroll
            htmlCells.forEach(function(cell){
                cell.style.left = String(iScrollLeft-iFixedColumnXPos) + "px";
                //đặt lề phải của các fixed column
                if(parseInt(cell.getAttribute("fixed")) === iRightMostColIdx){
                    cell.setAttribute("right-most-fixed-column","");
                }
            });
            this.preventEdgeCurrentCellCropping(iRightMostColIdx,isFixedColumnFromZero);
            frmControl.style.left = String(iScrollLeft - iFixedColumnXPos) +"px";
        }
        else{//về trang thái bình thường 
            htmlCells.forEach(function(cell){
                cell.style.left =  "0px";
                //xóa lề phải của các fixed column, dùng border-left của cột bên cạnh
                if(parseInt(cell.getAttribute("fixed")) === iRightMostColIdx){
                    cell.removeAttribute("right-most-fixed-column");
                }
            });
            this.isHScroll = false;
            this.preventEdgeCurrentCellCropping(iRightMostColIdx,isFixedColumnFromZero);
            frmControl.style.left = "0px";
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    getCurrentRowIdx(){
        let frmControl = this.frmControl;
        let iCurrentPageSize = frmControl["page_size"].oldKey;
        let iCurrentPage = common.isInteger(frmControl.querySelector("span[name=current-page]").innerText)-1;
        let htmlTHead = this.tableData.tHead;
        let currentRow = this.tableData.querySelector("tr[name='"+this.sSelectedRowKey+"']");
        let iRowIndex  = 0;
        if(currentRow){
            iRowIndex = currentRow.rowIndex - htmlTHead.querySelectorAll("TR").length;
        }
        return iCurrentPage*iCurrentPageSize+iRowIndex;
    };
    /*----------------------------------------------------------------------------------------------------*/
    //chuyển sang hàm arrow để this trỏ vào đúng table object chứ không phải combo object nữa
    //pageSize(inputPageSize){
    pageSize =(inputPageSize,valueChanged)=>{
        let autoTblObj = this;
        let frmControl = autoTblObj.frmControl;
        let iCurentPageSize    = frmControl["page_size"].oldKey;
        if(iCurentPageSize === common.isInteger(inputPageSize.value)){
            return;//page size hiện tại không thay đổi
        }
        let iPageSize = common.isInteger(inputPageSize.value);
        if(iPageSize===false) {
            return;
        }
        if(!autoTblObj.confirmExitTable()){
            return;
        }
        let iMaxPageSize = common.isInteger(autoTblObj.maxPageSize);//ép kiểu interger
        let iMinPageSize = common.isInteger(autoTblObj.minPageSize);//ép kiểu interger
        if(iPageSize < iMinPageSize){
            iPageSize = iMinPageSize;
        }
        if(iPageSize > iMaxPageSize){
            iPageSize = iMaxPageSize;
        }
        let iCurrentRowIndex =autoTblObj.getCurrentRowIdx();
        let iNewPage = Math.floor(iCurrentRowIndex/iPageSize);
        let sUrlGet  = common.setURLParam(autoTblObj.sUrlGet,"page_size",iPageSize);
        sUrlGet = common.setURLParam(sUrlGet,"page",iNewPage);
        autoTblObj.sUrlGet = sUrlGet;
        autoTblObj.loadData();
    };
    /*----------------------------------------------------------------------------------------------------*/
    clearHTMLTableAndFormControl(){
        //begin xóa form control
        let pPaging = this.frmControl.querySelector("p[name=paging]");
        let sText = common.replaceTextMarkup(pPaging.innerHTML,"","paging");
        sText = common.replaceTextMarkup(sText,"","from_row");
        sText = common.replaceTextMarkup(sText,"","to_row");
        sText = common.replaceTextMarkup(sText,"","num_row");
        pPaging.innerHTML = sText;
        if(!common.isEmpty(this.smallCboPageSize)){
            this.smallCboPageSize.clearCombo();
        }
        this.frmControl.style.display = "none";
        //end xóa form control
        this.tableData.innerHTML = "";//xóa table
        this.tableData.style.display = "none";
    };
    /*----------------------------------------------------------------------------------------------------*/
    /*Đặt lại sUrlGet của table và tất cả các link phân trang theo tham số sUrlGet truyền vào
     * 
     * @param {string} sUrlGet
     * @returns null
     */
    resetUrlGet(sUrlGet){
        this.sUrlGet = sUrlGet;
        let pPaging = this.frmControl.querySelector("p[name=paging]");
        let aTags = pPaging.querySelectorAll("A");
        for(let i=0; i<aTags.length;i++){
            let iPage = common.getURLParam(aTags[i].href,"page");
            let sUrl  = common.setURLParam(sUrlGet,"page",iPage);
            aTags[i].href = sUrl;
        }
        
    }
    /*----------------------------------------------------------------------------------------------------*/
    /*     
     * @param {int} iRowIdxStart: index của row bắt đầu đánh thứ tự. Ví dụ khi ta chèn 1 row mới vào table
     * thi iRowIdxStart sẽ là rowIndex của row mới tạo đó. Khi xóa một row thì iRowIdxStart là index của row
     * kế tiếp đến hết
     * @returns : Hệ thống đánh số cột ngoài cùng từ row có index là iRowIdxStart đến hết
     */
    setColumnOrder(iRowIdxStart){
        let tdOrders = this.tableData.querySelectorAll("tbody td.colOrder");
        let idx = iRowIdxStart+1-this.nRowTHead;
        for(let i= iRowIdxStart-this.nRowTHead;i<tdOrders.length;i++){
            tdOrders[i].innerHTML = idx;
            idx++;
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    /*Nếu formControl có độ rộng nhỏ hơn tableData thì dặt độ rộng của formControl = độ rộng của tableData 
     * Mục đích phục vụ cho việc fixedHeader và fixedColumn. 
     */
   
    /*----------------------------------------------------------------------------------------------------*/
    constructor(tableData,frmControl,sUrlGet,sSelectedRowKey,objAtbts={}){
        //arrAddRowId dùng làm name tạm thời cho các new row add vào table. Quy định các dữ liệu key field trong
        //database không được mở đầu bằng _. key field name cũng không được bắt đầu bằng ký tự _.
        this.arrAddRowId = ['_a','_b','_c','_d','_e','_f','_g','_h','_i','_j','_k','_l','_m','_n','_o','_p','_q','_r','_s','_t','_u','_v','_w','_x','_y','_z'];
        //Các kiểu control chứa dữ liệu khi save
        this.arrEditableControlDataType = ["checkbox","radio","combo","date","textbox"];
        //Các kiểu control chứa dữ liệu phải kiểm tra
        this.arrMustCheckControlDataType = ["combo","date","textbox"];
        this.arrNonDataControlType       = ["button"];
        this.idxAddRow          = 0;
        this.tableData          =  tableData;
        //this.initClientRectLeft = null;//giá trị này chỉ có ý nghĩa khi fixedUntilColumn >-1 hoặc fixedColumn >-1
        this.frmControl         = frmControl;
        this.smallCboPageSize = {}; //sẽ khởi tạo trong this.initEvent
        this.minPageSize  = 0;//this.createHTMLPaging sẽ set giá trị này
        this.maxPageSize  = 0;//this.createHTMLPaging sẽ set giá trị này
        //this.initClientRectTop = null;//giá trị này chỉ có ý nghĩa khi fixedHeader = true;
        this.sUrlGet            = sUrlGet;
        //this.sUrlPost           = sUrlPost;
        this.sSelectedRowKey    = sSelectedRowKey; /*key của row đang chứa control focus*/
        //this.iSelectedColumn     = -1; 
        this.atbts            = objAtbts;
        this.fields             = [];//chứa mô tả các trường dữ liệu theo column, sẽ tính lại trong createHTMLColGroupAndHead function
        this.keyFields          = [];//các field key,sẽ tính lại trong createHTMLColGroupAndHead function
        this.currentControl     = false;//control hiện đang focus
        this.currentCell        = false;//TD chứa control hiện đang focus
        this.isHScroll          = false;//chỉ có ý nghĩa khi dùng tính năng fixedUntilColumn hoặc fixedColumn
        //arrDeletedCacheAfterPost sử dụng để sau khi POST lên nếu có dữ liệu đã cached và bị thay đổi thì hệ thống sẽ tự thêm tham số NOCACHE 
        //vào request sau khi post clear và tạo lại cache mới
        this.arrDeletedCacheAfterPost = [];
        //customRespRequest thường là một hàm để tùy biến lại respRequest. Tình huống sử dụng ví dụ như dữ liệu food chứa trong respRequest không đủ
        // mà cần thêm một số dữ liệu khác như là food_type để bổ trợ thì sẽ sữ dụng function customRespRequest. Function này được gọi trong 
        // function loadData và postData
        this.currentResizeCol        = null;
        this.currentResizeColWidth   = null;
        this.currentPageX           = null;
        this.customRespRequest = (respRequest)=>{
            
        }
        //getCachedScripts thường là một hàm gọi trong function loadData để bổ sung các dữ liệu đã cached vào cho respRequest
        this.getCachedScripts =(respRequest)=>{
            
        }
        this.saveCachedScripts =(respRequest)=>{
            
        }
        this.afterPostData = (respRequest)=>{
            
        }
    }
}