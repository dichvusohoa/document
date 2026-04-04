/*
PROPERTIES:	
	Child		: 	Node con
	Next		:	Node anh em
	Infor		:	Ký tự chứa trong nút. Ký tự '*', được chèn làm ký tự kết thúc một từ. Sở di cần khái niệm ký tự tận cùng
					vì có trường hợp 1 từ là 1 phần của từ khác. Ví dụ từ "to" là một phần của từ "toy"
	NumOfWord	:	Số lượng từ tới nút dó. Ví dụ xét nút 'c'của từ "dictionary", tới nút 'c',NumOfWord =126
					diều đó có nghia rằng trong từ điển có 126 từ mở đầu bằng chuỗi "dic"
	Key			:	Phần thông tin bổ sung
					Ví dụ ta dua từ 'Hà Nội*' vào cây từ điển, và muốn bổ sung thêm thông tin "024" là mã diện thoại của Hà nội
					Ký tự '*' tận cùng từ sẽ có Key ='04'. Key thường chỉ có ở nút tận cùng 1 WORD	
*/

class charNode{
    constructor(chrInfor){
        this.child      =   null;//trật tự các character trong cùng 1 word		
	this.next       =   null;//trỏ vào word kế tiếp
	this.numOfWord  =   1;	
	this.infor      =   chrInfor;
	this.key        =   null;
    }    
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {charNode} chrNodeObj
     * @param {boolean} isAsc = true add is asc , add is desc
     * @param {type} sKeyOption: áp dụng khi add 1 từ mà key đã tồn tại
     *      "replace_key": thay thế key đã tồn tại
     *      "array_key" : bổ sung thêm key vào list các key cũ
     *      "": không làm gì cả  
     * @returns 
     *      null: thất bại
     *      {addNode, isNewWord} addNode là node add thêm vào . isNewWord báo xem có word mới hay không
     */
    addNode(chrNodeObj,isAsc,sKeyOption){
        if(chrNodeObj.infor ===null || this.infor === null){
            return null;//False
	}
        //Begin 2020-03-31. THêm đoạn code này để lọc các ký tự trắng liên tục đứng cạnh nhau
        if(this.infor === ' ' && chrNodeObj.infor === ' '){
            return {addNode:this, isNewWord:false};       
        }
        //End 2020-03-31. THêm đoạn code này để lọc các ký tự trắng liên tục đứng cạnh nhau
	if(this.child === null){
            this.child =chrNodeObj;
            return {addNode:chrNodeObj,isNewWord:false};
	}
	//FROM HERE IS PROCEESING FOR NEXT NODE
  	let tmpNode_1 = null;
	let tmpNode_2 = this.child;
	let strTodo;
	if(isAsc){
            strTodo = "((tmpNode_1 === null||tmpNode_1.infor.toLowerCase()<=chrNodeObj.infor.toLowerCase())&&(tmpNode_2 === null||chrNodeObj.infor.toLowerCase()<=tmpNode_2.infor.toLowerCase()))";
	}
	else{
            strTodo = "((tmpNode_1 === null||tmpNode_1.infor.toLowerCase()>=chrNodeObj.infor.toLowerCase())&&(tmpNode_2 === null||chrNodeObj.infor.toLowerCase()>=tmpNode_2.infor.toLowerCase()))";
		
	}
	while(!eval(strTodo)){
            tmpNode_1 = tmpNode_2;
            tmpNode_2= tmpNode_2.next;
	}
	//BEGIN: Ký tư vừa chèn dã tồn tại trong dãy node anh em
	if(tmpNode_1 !==null && tmpNode_1.infor.toLowerCase() === chrNodeObj.infor.toLowerCase()){
            if(chrNodeObj.key !== null){
                tmpNode_1.setKey(chrNodeObj.key,sKeyOption);
            }
            return {addNode:tmpNode_1,isNewWord:false};    
	}
	if(tmpNode_2 !== null && tmpNode_2.infor.toLowerCase() === chrNodeObj.infor.toLowerCase()){
            if(chrNodeObj.key !== null){
                tmpNode_2.setKey(chrNodeObj.key,sKeyOption);
            }
            return {addNode:tmpNode_2,isNewWord:false}; 
	}
	//END: Ký tư vừa chèn dã tồn tại trong dãy node anh em
	//BEGIN:Ký tự vừa chèn là ký tự mới 
	if(tmpNode_1 === null){
            //Co nghia la chrNodeObj la phan tu dau tien trong day anh em
            this.child=chrNodeObj;
	
	}else{	
            tmpNode_1.next = chrNodeObj;
	}
        chrNodeObj.next = tmpNode_2;
	return {addNode:chrNodeObj,isNewWord:true};
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {char} chrSearch
     * @param {type} isAsc
     * @returns [charNode] 
     */
    searchChr(chrSearch,isAsc){ 
        if(this.child === null){
            return null;//Không tìm th?y
        }
	let tmpNode_1 = null;
	let tmpNode_2 = this.child;
	let strTodo;
	if(isAsc){
            strTodo = "((tmpNode_1 === null||tmpNode_1.infor.toLowerCase()<=chrSearch.toLowerCase())&&(tmpNode_2 === null || chrSearch.toLowerCase()<=tmpNode_2.infor.toLowerCase()))";
	}
	else{
            strTodo= "((tmpNode_1 === null||tmpNode_1.infor.toLowerCase()>=chrSearch.toLowerCase())&&(tmpNode_2 === null || chrSearch.toLowerCase()>=tmpNode_2.infor.toLowerCase()))";
		
	}
	while(!eval(strTodo)){
            tmpNode_1 = tmpNode_2;
            tmpNode_2 = tmpNode_2.next;
	}
	//BEGIN: Ký tư vừa chèn dã tồn tại trong dãy node anh em
	if(tmpNode_1 !== null && tmpNode_1.infor.toLowerCase() === chrSearch.toLowerCase()){
            return tmpNode_1;
	}
	if(tmpNode_2 !== null && tmpNode_2.infor.toLowerCase() === chrSearch.toLowerCase()){
            return tmpNode_2;
	}
	//END: Ký tư vừa chèn dã tồn tại trong dãy node anh em
 	return null;//Không tìm thấy;
    }
    /*--------------------------------------------------------------------------------------------------*/
    getKey(){
	let chrNodeObj = this;
	while(chrNodeObj.infor !== "*"){
            chrNodeObj = chrNodeObj.child;
	}
	if(chrNodeObj === null)return null;
	return chrNodeObj.key;
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {thường là string hoặc int} sKey
     * @param {string} sOption
     *      "replace_key": thay thế key đã tồn tại
     *      "array_key" : bổ sung thêm key vào list các key cũ
     *      "": không làm gì cả  
     * @returns tùy theo sOption
     */
    setKey(sKey,sOption){
        if(this.child!==null || this.infor !=="*"){
            return null; //không phải là nút tận cùng của 1 word thì không set key
        }
        if(this.key === null){
            this.key = sKey;
            return sKey;
        }
        if(this.key === sKey){
            return sKey;
        }
        //from here là this.key đã tồn tại và khác với sKey
        if(sOption === "replace_key"){
            this.key = sKey;
            return sKey;
        }
        else if(sOption === "array_key"){
            if(Array.isArray(this.key)){
                if(!this.key.includes(sKey)){
                    let tmp = this.key;
                    tmp.push(sKey);
                    this.key = tmp;
                }
            }
            else{
                this.key = [this.key,sKey];
            }
            return this.key; //2022-11-08
        }
        else{//không làm biến đổi key
            return this.key;
        }
    }
}
class dictTree{
    constructor(){
	this.rootNode = new charNode('*'); //để tất cả các word add vào tree có chung 1 gốc là *
	this.isAscending =true;
        /*optAddExistWord có các giá trị
         * "replace_key"  :   khi add word vào và word đã tồn tại thì key bị thay thế
         * "array_key"    :   khi add word vào và word đã tồn tại thì key tích lũy lại vào trong một mảng key
         * ""             :   khi add word vào và word đã tồn tại thì không làm gì    
         */
        this.optAddExistWord = "";
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {string} strWord
     * @param {string, int, object...} objKey: key của word add vào
     * @returns 
     * null: add word bị lỗi
     * true: add word là từ mới
     * false: add word cũ đã có trong hệ thống
     */
    addWord(strWord, objKey ){
	let iPos=0;
	let arrNode = new Array();
	arrNode[0]=this.rootNode;
	/*BEGIN: thực hiện chèn các ký tự tự 1->length-1;. Ví dụ từ ".dictionary*", ta sẽ bắt đầu đưa các ký tự
	tự d->* vào trong tree
	*/
	strWord =	strWord.replace(/\*/g,'')//Cắt hết các ký tư '*'
	//Thêm 2 ký tự '*' vào đầu và cuối chuỗi, 
	//ký tự '*' kết thúc chuỗi, ký tự '*' đầu chuỗi để cho tất cả tree có cùng 1 nút gốc
	strWord =	'*'+strWord+'*'; 
        for(let i=1;i<strWord.length;i++){
            let chr = strWord.charAt(i);
            let chrNodeObj = new charNode(chr);
            if(i === strWord.length-1){
                if(objKey){
                    chrNodeObj.key = objKey;
                }
            }
            let result= arrNode[i-1].addNode(chrNodeObj,this.isAscending,this.optAddExistWord);
            if(result === null){
                return null;
            }
            else{
                arrNode[i] = result.addNode;
                if(result.isNewWord){
                    iPos =i;
                }
            }
	}
	if(iPos>0){
            //Có xảy ra việc chèn thêm từ mới, cập nhật lại số lượnng word của các nút tổ tiên của nút iPos
            for(let i=0;i<iPos;i++){
                arrNode[i].numOfWord =  arrNode[i].numOfWord+1;
            }
            return true;
	}
	else return false;//Khong chen them tu moi;
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {charNode} chrNodeStart: node bắt đầu duyệt
     * @param {string} strPrefixWord
     * @param {int} iMaxSearch: kết quả search tối đa. = 0 tức là search toàn bộ
     * @param {object} objOutputResult có cấu trúc {objKey1: value1,objKey2:value2.......}
     * @param {object} objOutputNumWord: {count: iValue}. Số từ search được
     * @returns objOutputResult,objOutputNumWord
     */
    surfTree (chrNodeStart,strPrefixWord,iMaxSearch,objOutputResult,objOutputNumWord){
        if(chrNodeStart.child !== null){
            this.surfTree(chrNodeStart.child,strPrefixWord+chrNodeStart.infor,iMaxSearch,objOutputResult,objOutputNumWord);
        }
        else{//đây là nút * đánh dấu hết của 1 Word. chrNodeStart.child = null và chrNodeStart.infor = "*"
            //console.log("---------:"+strPrefixWord);
            if(iMaxSearch === 0 || objOutputNumWord["count"] <iMaxSearch){
                let arrKey = [];
                if(Array.isArray(chrNodeStart.key)){
                    arrKey = chrNodeStart.key;
                }
                else{
                    arrKey[0] = chrNodeStart.key;
                }
                for(let sKey of arrKey){
                    if(!objOutputResult[sKey]){
                        //let txt =(strPrefixWord+chrNodeStart.infor).replace(/\*$/g,'');//Cắt các dấu * ở duôi đi  
                        //objOutputResult[sKey]= txt;
                        objOutputResult[sKey]= strPrefixWord;
                        objOutputNumWord["count"] = objOutputNumWord["count"]+1;
                        if(iMaxSearch!==0 && objOutputNumWord["count"]>=iMaxSearch){
                            break;
                        }
                    }
                    
                }
            }
        }
        if(chrNodeStart.next !== null &&(iMaxSearch === 0 || objOutputNumWord["count"]<iMaxSearch)){
            this.surfTree(chrNodeStart.next,strPrefixWord,iMaxSearch,objOutputResult,objOutputNumWord);
        }	
    }//end function surfTree
    /*--------------------------------------------------------------------------------------------------*/
    surfTree2(chrNodeSurf,strPrefixWord){
    	if(chrNodeSurf.child !== null){
            this.surfTree2(chrNodeSurf.child,strPrefixWord+chrNodeSurf.infor);
    	}
    	else{
            //console.log(strPrefixWord.replace(/^\*/g,''));//khi in ra thì bỏ ký tự * ở đầu đi
            console.log(strPrefixWord+chrNodeSurf.infor);//khi in ra thì bỏ ký tự * ở đầu đi
            console.log(chrNodeSurf.key);
    	}
    	if(chrNodeSurf.next!== null){
            this.surfTree2(chrNodeSurf.next,strPrefixWord);
    	}
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * @param {string} strSearch
     * @returns {"chrNode": chrNodeStartSurf,"chainChar":strPrefix}
     * chrNodeStartSurf dạng charNode
     */
    searchStrToCharNode(strSearch){
        let chrNodeStartSurf ;//Nút bắt đầu duyệt các từ kết quả
        let strPrefix = "";
        if(strSearch===''){
            chrNodeStartSurf =  this.rootNode;
        }
        else{
            strSearch =	strSearch.replace(/\*/g,'')//Cắt hết các ký tự '*'
            //Thêm ký tự '*' vào đầu  chuỗi, 
            //ký tự '*' đầu chuỗi để cho tất cả tree có cùng 1 nút gốc
            strSearch =	'*'+strSearch; 
            let chrNodeTmp = this.rootNode;
            let i=1;//bỏ ký tự * ở đầu
            while(i===1||(i<strSearch.length && chrNodeStartSurf !== null)){
                let chr = strSearch.charAt(i);
                chrNodeStartSurf = chrNodeTmp.searchChr(chr,this.isAscending);
                if(chrNodeStartSurf!==null){
                    strPrefix    = strPrefix + chrNodeStartSurf.infor;    
                }
                chrNodeTmp = chrNodeStartSurf ;
                i++ ; 
            }
        }
        if(chrNodeStartSurf === null){
            return null;
        }
        /*chainChar lưu lại dãy thứ tự các ký tự lưu trong cây từ điển đã duyệt qua
         về lý thuyết thì chainChar.toLowerCase() === strSearch.toLowerCase(). Nhưng
         ta sử dụng chainChar vì nó lưu trữ chính xác các ký tự trong cây từ điển
         không bị nhầm lẫn viết hoa viết thường*/
        return {"chrNode": chrNodeStartSurf,"chainChar":strPrefix};
    }
    /*--------------------------------------------------------------------------------------------------*/
    /*
     * 
     * @param {string} strSearch
     * @param {int} -1 tức là không hạn chế số lượng kết quả tìm kiếm
     * @returns {object} objOutputResult có cấu trúc {key1: str1, key2: str2,....} đó là kết quả tìm kiếm
     * nếu không tìm thấy thì sẽ trả về kết quả null
     */
    search(strSearch,iMaxSearch){
        let obj = this.searchStrToCharNode(strSearch);
        if(obj === null){
            return null;
        }
        let objOutputResult = {};
        let objOutputNumWord = {count:0};
        //Sử dụng obj.chainChar chứ không dùng strSearch để lấy chính xác các ký tự đã lưu trong từ điển, không phụ thuộc vào Viết hoa hay Viết thường
        this.surfTree(obj.chrNode.child ,obj.chainChar,iMaxSearch,objOutputResult,objOutputNumWord);
        return objOutputResult;
    }
    /*----------------------------------------------------------------------*/
    static isEndWordNode(chrNode){
        return (chrNode.child === null && chrNode["infor"] === "*");
    }
    /*----------------------------------------------------------------------*/
    /*Nếu strSearch ứng với nhiều word (>1) hoặc không ứng với word nào return null
     nếu ứng với chỉ duy nhất một word thì return key*/
    searchExact(strSearch){
        let obj = this.searchStrToCharNode(strSearch);
        if(obj === null){
            return null;
        }
        let chrNodeChild =  obj.chrNode.child;
       // let isEndOfWord = chrNodeChild.child === null  && chrNodeChild["infor"] ==="*";
        if(!dictTree.isEndWordNode(chrNodeChild)){
            return null;
        }
        return chrNodeChild.key;
    }
    /*----------------------------------------------------------------------*/
    printTree(){
        this.surfTree2(this.rootNode,'');
    }
    /*----------------------------------------------------------------------*/
}    
