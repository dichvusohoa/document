class searchCombo{
    static SEARCH_MODE_PREFIX   = 0;  // strValue là phần mở đầu của 1 item trong combo
    static SEARCH_MODE_CONTAINS = 1;  // strValue là khúc nào đó trong item trong combo
    static SEARCH_MODE_WORD     = 2;  // strValue là một từ trong item trong combo
    /*ok function for searchCombo object. Đặt active. Dùng để active item   */
    hoverItem(item){
        let divListItem    = this.divListItem;
        let items           = divListItem.querySelectorAll("DIV");
        let idx = -1;
        for(let i=0;i<items.length;i++){
            if(item === items[i]){
                items[i].setAttribute("hover",true); 
                idx = i;   
            }
            else{
                items[i].removeAttribute("hover");
            }
        }
        if(idx === -1){
            return;
        }
        /*Begin: đoạn code trên chủ yếu dùng khi ấn phím Arrow Up and Arrow Down để chuyển phần tử active.
        nếu phần tử active bị trôi quá xuống đáy hoặc lên đỉnh của khung view thì cẩn chỉnh scroll bar để 
        phần tử active không bị che khuất*/
        if(this.numItemsPerPage < items.length){//có thanh scroll-Y
            let iHeight     =  divListItem.clientHeight/this.numItemsPerPage;
            if(divListItem.scrollTop > idx*iHeight){
                divListItem.scrollTop = idx*iHeight;
                this.setScroll_Y = true;//kéo thanh scroll bằng code không phải bằng tay, nó sẽ có thể làm phát sinh onover event
            }
            else if(divListItem.scrollTop < (idx + 1 - this.numItemsPerPage)*iHeight){
                divListItem.scrollTop = (idx + 1 - this.numItemsPerPage)*iHeight;
                this.setScroll_Y = true;//kéo thanh scroll bằng code không phải bằng tay, ó sẽ có thể làm phát sinh onover event
            }
        }
        /*End: đoạn code trên chủ yếu dùng khi ấn phím Arrow Up and Arrow Down để chuyển phần tử active.
        nếu phần tử active bị trôi quá xuống đáy hoặc lên đỉnh của khung view thì cẩn chỉnh scroll bar để 
        phần tử active không bị che khuất*/
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*ok function for searchCombo object. Tìm kiếm phần tử active tiếp theo */
    createItem(divListItem,key,sItemName){
        let divItem = document.createElement('DIV');
        divItem.style.height = this.itemHeight;
        /*Phải set min-height ở mode flex vì nếu vì nếu list-items quá dài thì nó sẽ
        //tự co nhỏ chiều cao của item lại cho đủ số item nên set min-height để chặn việc co rút chiều cao này*/
        divItem.style.minHeight = this.itemHeight;
        divItem.innerHTML = sItemName;
        divItem.key=key;
        divListItem.appendChild(divItem);
        return divItem;
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*
     * @param {string} strValue
     * @returns {int} số phần từ search được
     */
    search(strValue){
        let jsonData =  this.jsonData;
        if(common.isEmpty(jsonData)){
            return 0;
        }
        let divListItem = this.divListItem;
        divListItem.innerHTML=""; //xóa hết kết quả cũ
        let iNumItem = 0;
        let key;
        let sVal;
        let isMatch = false;
        for(key in jsonData){
            sVal = String(jsonData[key]);
            isMatch = false;
            switch(this.searchMode){
                case searchCombo.SEARCH_MODE_PREFIX:
                    //strValue là một phần mở đầu của 1 item trong combo
                    isMatch =(sVal.substring(0, strValue.length).toUpperCase() === strValue.toUpperCase());
                    break;
                case searchCombo.SEARCH_MODE_CONTAINS:
                    //strValue là khúc nào đó trong 1 item trong combo
                    isMatch = (sVal.toUpperCase().indexOf(strValue.toUpperCase())>=0);
                    break;
                case searchCombo.SEARCH_MODE_WORD:
                    //Reference: https://shiba1014.medium.com/regex-word-boundaries-with-unicode-207794f6e7ed
                    //strValue là một từ của 1 item trong combo
                    if(strValue===""){
                        isMatch = true;
                    }
                    else{
                        isMatch = new RegExp("(?<=[\\s]|^)" + strValue + "(?=[\\s]|$)","i").test(sVal);
                    }
                    break;
                default:
                    isMatch = false;
                    break;
            }
            if (isMatch) {
                this.createItem(divListItem,key,sVal);
                iNumItem++;
            }
        }
        if(iNumItem>this.numItemsPerPage){//có scroll
            divListItem.style.height = `calc(${this.itemHeight}*${this.numItemsPerPage})`;
        }
        else{
            divListItem.style.height = `calc(${this.itemHeight}*${iNumItem})`;
        }
        return iNumItem;
    };
    /*-------------------------------------------------------------------------------------------------*/
     /*Description: sở dĩ vẫn cần dùng hàm static vì sau này dùng combo trong table thì không phải lúc
     *nào lấy key value cũng có combo object để dùng mà phải gọi qua hàm 
     * @param {string} strValue
     * (object)jsonData
     * @returns:
     * + Nếu strValue === "" 
     *  -   isNullable thì return null
     *  -   isNullable = false và có defaultKey thì return defaultKey
     *  -   isNullable = false và không có defaultKey return {"required":"Giá trị không được để trống"};
     * + Nếu strValue !==""
     *      return key nếu tìm thấy giá trị tương ứng
     *      return {"must_be_in_list":"Giá trị phải nằm trong danh sách"} nếu nằm ngoài danh sách và bị must_be_in_list
     *      return strValue nếu nằm ngoài danh sách không bị must_be_in_list
     */
    static getCmbKey(strValue,jsonData,defaultKey,constraints){
        if(strValue === ""){
            let isRequired = constraints && constraints.hasOwnProperty("required") && constraints["required"];
            if(!isRequired){
                return null;
            }//from here constraints["required"] === true;
            else if(defaultKey){
                return defaultKey;
            }
            else{
                return {"required":ERR_DATA["required"]};
            }
        }
        //From here là giá trị !==""
        let key = common.getKeyByValueInJSONData(strValue,jsonData);
        if(key !== null){
            return key;
        }
        //from here giá trị ra ngoài danh sách và !==""
        if(constraints === null){//không có ràng buộc
            return strValue;// ra ngoài danh sách, return lại chính value đó
        }
        //from here có constraints
        if(constraints.hasOwnProperty("must_be_in_list") && constraints["must_be_in_list"]){
            return {"must_be_in_list":ERR_DATA["must_be_in_list"]};
        }
        return strValue;// ra ngoài danh sách, nhưng không vi phạm constraints nào return lại chính value đó
    }
    /*-------------------------------------------------------------------------------------------------*/
    validate(){
        if(this.isValidated === false){
            this.key = searchCombo.getCmbKey(this.inputDest.value.trim(),this.jsonData,this.defaultKey,this.constraints);
            this.isValidated = true;
        }
        if(ERR_DATA.isErrData(this.key)){
            return false;
        }
        return true;
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*
     * @param {string} strValue
     * @returns key của strValue. Nếu không có giá trị tương ứng thì return null
     */
    getKey(){
        //return searchCombo.getCmbKey(strValue,this.jsonData,this.defaultKey,this.constraints);
        if(this.isValidated === false){
            this.key = searchCombo.getCmbKey(this.inputDest.value.trim(),this.jsonData,this.defaultKey,this.constraints);
            this.isValidated = true;
        }
        return this.key; 
    };
    /*-------------------------------------------------------------------------------------------------*/
    triggerInputCommit(eventname){
        if (typeof this.onInputCommit !== 'function' && typeof this.onInputCommitError !== 'function') {
            return;
        }
        let key =  this.getKey();
        let isChanged = !common.deepEqual(key,this.prevKey); 
        if(ERR_DATA.isErrData(key)){
            if (typeof this.onInputCommitError === 'function') {
                this.onInputCommitError(this.inputDest, key, isChanged, eventname);
                this.prevKey = key;//ghi lại giá trị trước đó, dù là có lỗi hay không
            }
            return;
        }
        if (typeof this.onInputCommit === 'function'){
            /*Sở dĩ vẫn kích hoạt onInputCommit ngay cả khi isChanged === false vì
            tổng quát sau này có khả năng ngay cả khi isChanged = false vẫn phải làm
            một tác vụ gì đó, */
            this.onInputCommit(this.inputDest,key,isChanged,eventname);
            this.prevKey = key;
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*Lưu ý là khi thực hiện chọn các item nhiều lần thì chỉ có giá trị key của inputDest
     * thay đổi còn oldKey vẫn giữ giá trị lúc khởi tạo combo. Điều này đẻ giúp cho lúc cuối cùng
     * khi submit form thì xác định xem được có xảy ra thay đổi giá trị không */
    itemClick(divItem){  
      //  let inputDest = this.inputDest;
       // let isChanged = !common.isEqual(divItem.key,inputDest.oldKey); 
        /*End update 2024-03-22*/
        this.divListItem.style.display = 'none';  
        //this.key = e.target.getAttribute("data-key");
        this.inputDest.value      = divItem.innerHTML; 
        this.key = divItem.key;
        this.inputDest.removeAttribute("match");  
        /*cho hàm getKey trong triggerInputCommit lấy thẳng giá trị từ this.key*/
        this.isValidated = true;
        this.triggerInputCommit("selectitem");                
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event hander cho event input của inputDest  */  
    input =()=>{
        let inputDest = this.inputDest;
        let iNumItem    = this.search(inputDest.value);
        let divListItem = this.divListItem;
        let itemActive; 
        if(iNumItem){
            itemActive = divListItem.firstChild;
            itemActive.setAttribute("hover","true");
            inputDest.setAttribute("match","true");
            divListItem.style.display = 'flex';  
        }
        else{
            inputDest.setAttribute("match","false");//băt lỗi màu đỏ
            divListItem.style.display = 'none';
        }
        this.isValidated = false;//phải validate lại
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler của divCombo*/
    click = (event)=>{
        if(event.target === this.inputArrow){
            this.showHideListItem();
        }
        else if(event.target.parentNode === this.divListItem){
            this.itemClick(event.target);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler của divCombo: dùng cho cả inputDest, inputArrow, divListItem*/
    keyDown = (event)=>{
        let keyCode = event.key;
        if(keyCode === "Enter"){
            if(event.target === this.inputDest || event.target === this.divListItem){
                this.inputCommit();
            }
        }
        else if(keyCode === "Escape"){//ok
            event.stopPropagation();//chặn bubble để nó thôi không bắt event ở  TBODY nữa (dùng cùng với tTable)
            this.divListItem.style.display='none';
        }
        else if(keyCode === "ArrowUp" || keyCode === "ArrowDown"){//ArrowUp+ArrowDown
            event.stopPropagation();
            this.keyDownArrowUpAndDown(event);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler của divCombo*/
    mouseLeave =(event)=>{
        let objSmallCmb = this;
        setTimeout(function(){ 
            //let divListItems = divCombo.querySelector("DIV");
            let divListItem = objSmallCmb.divListItem;
            if(divListItem){
                divListItem.style.display='none';
            } 
        },10);
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler của divCombo
     * Reference: https://gomakethings.com/detecting-when-focus-leaves-a-group-of-elements-with-vanilla-js
     * https://adueck.github.io/blog/keep-focus-when-clicking-on-element-react/
     */
    focusOut =(event)=>{
        /*event.relatedTarget === null xảy ra khi ta click xung quanh inputDest và inputArrow nhưng không click vào
         loại control có tabIndex nào (INPUT, TEXTAREA, SELECT, A,...). Ta cần đoạn code giữ focus vào
        inputDest hoặc inputArrow này để khi ta click vào các divItem thì nó sẽ chặn không cho chạy hàm
         this.onComboOut(this.divCombo), vì nếu chạy hàm này thì sẽ mất event click của các divItem (
         được thiết lập ở: divCombo.addEventListener("click",this.click); ) 
         event.relatedTarget đại diện cho phần tử sẽ nhận được focus tiếp theo
         */
        if(event.relatedTarget === null){
            event.target.focus();
            return;
        }
        /* đoạn code dưới xảy ra khi chuyển focus giữa các phần tử inputDest,inputArrow,
         * divListItem (chú ý rằng khi divListItem xuất hiện scroll thì nó có thể nhận được
         * focus)*/
        if (this.divCombo.contains(event.relatedTarget)) {
            return;
        }
        //from here là thật sự out ra ngoài
        this.divListItem.style.display='none';
        this.triggerInputCommit("focusout");
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*event handler của divCombo*/
    mouseOver =(event)=>{ 
        if(event.target.parentNode !== this.divListItem){
            return;
        }
        if(this.setScroll_Y){//Khi không có kéo Scroll bằng code thì không làm gì cả vì sẽ không chính xác
            this.setScroll_Y = false;
        }
        else{
            this.hoverItem(event.target);  
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    showHideListItem(){
        let inputDest =  this.inputDest;
        let divListItem = this.divListItem;
        if(divListItem.style.display === 'flex'){
            divListItem.style.display = 'none';    
            inputDest.focus(); 
            return;  
        }
        /*bấm arrow down thì xổ xuống toàn bộ các value để người dùng biết là có
         * tất cả các giá trị gì để lựa chọn*/
        let numItems = this.search("");//
        if(numItems > 0){
            divListItem.style.display = 'flex';
        }
        inputDest.focus();  
    }
    /*-------------------------------------------------------------------------------------------------*/
    inputCommit(){
        let divListItem = this.divListItem;
        if(divListItem.style.display === "none"){
            this.triggerInputCommit("keyenter");//tình huống Enter vào inputDest
            return;
        }
        let hoveredItem = divListItem.querySelector("div[hover]");
        if(hoveredItem){
            this.itemClick(hoveredItem);
        }
        else{//tình huống Enter vào inputDest không có phần tử nào hover 
            this.triggerInputCommit("keyenter");
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    nextPreviousHoveredItem(isNext){
        let divListItem = this.divListItem;
        let hoveredItem = divListItem.querySelector("div[hover]");
        let item = null;
        if(isNext){
            item = hoveredItem ? hoveredItem.nextSibling : divListItem.firstChild;
        }
        else{
            item = hoveredItem ? hoveredItem.previousSibling : divListItem.firstChild;
        }
        if(item){
            this.hoverItem(item);
        }
    }
    /*-------------------------------------------------------------------------------------------------*/
    nextPreviousItem(isNext){
        if(!this.jsonDataKey){
            this.jsonDataKey = this.jsonData ? Object.keys(this.jsonData) : null; //dùng cho nextPreviousItem
        }
        if(!this.jsonDataKey){
            return;
        }
        let currentKey = this.getKey();
        let currentIdx = this.jsonDataKey.indexOf(currentKey);
        if(currentIdx === -1){
            return;
        }
        let idx = isNext ? currentIdx + 1: currentIdx -1;
        if(idx <0 || idx >= this.jsonDataKey.length){
            return;
        }
        let newKey = this.jsonDataKey[idx];
        this.inputDest.value = this.jsonData[newKey];
        this.key = newKey;
        /*cho hàm getKey trong triggerInputCommit lấy thẳng giá trị từ this.key*/
        this.isValidated = true;
        this.triggerInputCommit("keyupdown");
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownArrowUpAndDown(event){
        let keyCode = event.key;
        if(event.ctrlKey){
            this.showHideListItem();
            return;
        }
        let isNext = (keyCode === "ArrowDown");
        let isNextPrevItem = this.divListItem.style.display === "none";
        if(isNextPrevItem){
            this.nextPreviousItem(isNext);
        }
        this.nextPreviousHoveredItem(isNext);
    }
    /*-------------------------------------------------------------------------------------------------*/
    /*function for searchCombo object*/
    clearCombo(){
        let divCombo    =   this.divCombo;
        if(divCombo && divCombo.tagName === "DIV" && divCombo.className === this.comboClassName){//Đề phòng bị xóa từ trước thì không xóa lại nữa
            let parent      =   divCombo.parentNode;
            if(parent){
                let inputDest = this.inputDest;
                //xóa hết event handler gắn vào
                inputDest.removeEventListener("input",this.input);
                parent.insertBefore(inputDest, divCombo); 
                common.removeAllChild(divCombo);//bổ sung 2024-02-19
                parent.removeChild(divCombo);      
            }
        }
    };
    /*-------------------------------------------------------------------------------------------------*/
    initEvent(){
        /*Begin đặt event handler cho các phần tử. Chú ý phần này bao giờ cũng phải đặt xuống
         * dưới cùng khi các properties của Object đã được thiết lập ổn định thì event handler chạy
         * mới chính xác. Nếu đặt event handler ở giữa khởi tạo các thành phần của object thì có
         * thể một số event ví dụ blur, focus ... sẽ chạy trước cả khi khởi tạo xong object =>
         * sẽ không chính xác*/
        this.inputDest.addEventListener("input",this.input);
        this.divCombo.addEventListener("click",this.click);
        this.divCombo.addEventListener("keydown",this.keyDown);
        this.divCombo.addEventListener("mouseleave",this.mouseLeave);
        this.divCombo.addEventListener("focusout",this.focusOut);
        this.divCombo.addEventListener("mouseover",this.mouseOver);
        
    }
    /*-------------------------------------------------------------------------------------------------*/
    constructor(inputDest,jsonData,defaultKey=null,options={}){
        this.inputDest  = inputDest;
        this.jsonData   = jsonData;
        this.jsonDataKey = null; //dùng cho nextPreviousItem, sau này sẽ tạo ra trong nextPreviousItem
        this.comboClassName = options.hasOwnProperty("comboClassName") ? options.comboClassName : 'search-combo';//sau này dùng cho clearCombo
        //this.itemHeight = options.hasOwnProperty("itemHeight") ? options.itemHeight: inputDest.clientHeight +"px";
        this.numItemsPerPage        = options.hasOwnProperty("numItemsPerPage") ? options.numItemsPerPage: 10;
        this.onInputCommit          = typeof options.onInputCommit === 'function' ? options.onInputCommit : null;
        this.onInputCommitError     = typeof options.onInputCommitError === 'function' ? options.onInputCommitError : null;
        //this.onComboOut     = typeof options.onComboOut === 'function' ? options.onComboOut : null;//2024-04-22
        this.searchMode = options.hasOwnProperty("searchMode") ? options.searchMode : searchCombo.SEARCH_MODE_CONTAINS;
        this.defaultKey = defaultKey;
        this.constraints = options.hasOwnProperty("constraints") ? options.constraints : null;
        this.isValidated  = false; // chưa validate dữ liệu
        //this.isChanged  = false;//chưa có sự thay đổi dữ liệu
        this.key = null;
        if(this.onInputCommit || this.onInputCommitError){
            this.key = this.getKey();
            this.prevKey = this.key; 
        }
        /*Begin: Tạo một thẻ DIV to bao quanh phía ngoài*/	
        let divCombo = document.createElement('DIV');
        divCombo.className = this.comboClassName;
        let parent = inputDest.parentNode;
        parent.insertBefore(divCombo,inputDest);/*Chèn thẻ DIV vào trong container chứa thẻ INPUT inputDest*/
        if(options.hasOwnProperty("zIndex")){
            divCombo.style.zIndex = options.zIndex;//2020-07-13
        }
        divCombo.appendChild(inputDest);/*Nhúng thẻ INPUT inputDest vào trong thẻ DIV*/
        /*End: Tạo một thẻ DIV to bao quanh phía ngoài*/
         /*Begin: Tạo một thẻ INPUT chứa ảnh mũi tên */
        let inputArrow = document.createElement('INPUT');
        inputArrow.type      = 'button';
       // inputArrow.style.flex = "0 0 calc(0.8*"+this.itemHeight+")";
        inputArrow.style.flex = `0 0 calc(0.8*${divCombo.offsetHeight}px)`;
        this.inputArrow = inputArrow; 
        divCombo.appendChild(inputArrow);
        /*End: Tạo một thẻ INPUT chứa ảnh mũi tên*/
        this.itemHeight = options.hasOwnProperty("itemHeight") ? options.itemHeight: divCombo.clientHeight +"px";
         /*Begin: Tạo một thẻ DIV thứ 2 chứa các items */
        let divListItem = document.createElement('DIV');
        divListItem.style.display = 'none';
        //divListItem.style.top = "calc("+this.itemHeight+")";
        divListItem.style.top = `calc(${divCombo.offsetHeight}px)`;
        //divListItem.style.top = `calc(${divCombo.offsetHeight-7}px)`;
        this.divListItem = divListItem; 
        divCombo.appendChild(divListItem);
        /*End: Tạo một thẻ DIV thứ 2 chứa các items */
        this.divCombo = divCombo; 
        /*End: Tạo một thẻ DIV chứa thẻ INPUT inputDest*/
        if(inputDest.hasAttribute("new")){
            divListItem.setAttribute("new",inputDest.getAttribute("new"));        
        }
        this.initEvent();
    }
}
/*-------------------------------------------------------------------------------------------------*/
