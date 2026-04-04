class treeSearchCombo extends searchCombo{
    /*
     * 
     * @param {string} strValue
     * @returns {int} số phần tử tìm kiếm được
     */
    search(strValue){
        if(common.isEmpty(this.jsonData)){
            return 0;
        }
        let divListItem = this.divListItem;
        divListItem.innerHTML=""; 
        let objSearch = this.dictTreeData.search(strValue,this.maxSearch);
        let iNumItem = 0;
        for(let key in objSearch){
            let sVal = String(this.jsonData[key]);
            this.createItem(divListItem,key,sVal,this.itemHeight);
            iNumItem++;
        }
        return iNumItem;
    };
    /*-------------------------------------------------------------------------------------------------*/
    static getTreeCmbKey(strValue,jsonData,dictTreeData,defaultKey,constraints){
        if(strValue === ""){
            return searchCombo.getCmbKey(strValue,null,defaultKey,constraints);
        }
        let key = dictTreeData.searchExact(strValue);
        if(key === null){
            //from here là giá trị !=="" và ra ngoài danh sách rồi
            return searchCombo.getCmbKey(strValue,null,defaultKey,constraints);
        }
        if(Array.isArray(key)){//trường hợp
            for(let i = 0; i < key.length; i++){
                if(jsonData[key[i]].toLowerCase() === strValue.toLowerCase()){
                    return key[i];
                }
            }
            return {"must_be_in_list":ERR_DATA["must_be_in_list"]};
        }
        if(jsonData[key].toLowerCase() === strValue.toLowerCase()){
            return key;
        }
        return {"must_be_in_list":ERR_DATA["must_be_in_list"]};
    };
    /*-------------------------------------------------------------------------------------------------*/
    getKey(){
        if(!this.dictTreeData){
            return {"dict_tree_not_found":ERR_DATA["dict_tree_not_found"]};
        }
        return treeSearchCombo.getTreeCmbKey(this.inputDest.value.trim(),this.jsonData,this.dictTreeData,this.defaultKey,this.constraints);
    };
    /*-------------------------------------------------------------------------------------------------*/
    /*chỉ xảy ra khi this.isBigData === false*/
    nextPreviousItem(isNext){
        if(this.isBigData){
            return;
        }
        if(!this.jsonDataKey){
            let objSearch = this.dictTreeData.search("",0);
            this.jsonDataKey = Object.keys(objSearch);
        }
        super.nextPreviousItem(isNext);
    }
    /*-------------------------------------------------------------------------------------------------*/
    keyDownArrowUpAndDown(event){
        let keyCode = event.key;
        let isNext = (keyCode === "ArrowDown");
        if(this.isBigData){
            if(this.divListItem.style.display !== "none"){
                this.nextPreviousHoveredItem(isNext);
            }
            return;
        }
        super.keyDownArrowUpAndDown(event);
    }
    /*-------------------------------------------------------------------------------------------------*/
    createDictTree(){
        this.dictTreeData = common.createDictTree(this.jsonData,"array_key");
    //    this.dictTreeData.printTree();
    }
    /*-------------------------------------------------------------------------------------------------*/
    // jsonData ở đây vẫn là cấu trúc key/value.
    constructor(inputDest,jsonData,dictTreeData,defaultKey=null,objAtbts={}){
        super(inputDest,jsonData,defaultKey,objAtbts);
        this.isBigData    = objAtbts.hasOwnProperty("isBigData") ? objAtbts.isBigData: true;
        this.maxSearch = objAtbts.hasOwnProperty("maxSearch") ? objAtbts.maxSearch : 10;//số kết quả searh tối đa
        if(dictTreeData){
            this.dictTreeData = dictTreeData;
        }
        else{
            this.createDictTree();
            inputDest.key = this.getKey();
            inputDest.oldKey = inputDest.key;
        }
        if(this.isBigData){
            this.inputArrow.style.display = "none";
        }
        //console.log(this.jsonDataKey);
    }
}
/*-------------------------------------------------------------------------------------------------*/
