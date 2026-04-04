import { LoadingOverlay } from 'loading';
export class AutoForm{
   /*----------------------------------------------------------------------------------------------------*/
    showError(err){
        //chưa có code
    };
    /*----------------------------------------------------------------------------------------------------*/
    isValidate(){
        //chưa có code
        return {};
    }
    /*----------------------------------------------------------------------------------------------------*/
    collectData(sAction){
        //chưa có code tổng quát, hiện tạm thời làm trường  hợp cá biệt chỉ có user_name và password
        /*không nên đặt tên action vì trùng với html action mặc định của form, đó là port url*/
        /*return {"user_name" : this.frmControl['user_name'].value, 
        "password" : this.frmControl['user_password'].value,
        
        "submit_action" : sAction
        };*/
        const formData = new FormData(this.frmControl);
        formData.append("user[login]", this.frmControl['user[login]'].value);
        formData.append("user[password]", this.frmControl['user[password]'].value);
        formData.append("submit_action", sAction);
        return formData;
    }
    /*----------------------------------------------------------------------------------------------------*/
    initEvent(){
        this.frmControl.addEventListener("submit",this.submit);
        this.frmControl.addEventListener("keydown",this.keyDown);
    }
    /*----------------------------------------------------------------------------------------------------*/
    submit = (event) =>{
        //chặn không để form submit thông thường mà sẽ dùng fetch để submit
        event.preventDefault();
        //chỉ chạy tiếp khi có submitter và action submit rõ ràng
        if(!event.submitter || !event.submitter.dataset.action){
            return;
        }
        const action = event.submitter.dataset.action;
        if (action === "delete" && !confirm("Bạn có chắc muốn xóa?")) {
            return;
        }
        let err = this.isValidate();
        if(!common.isEmpty(err)){
            this.showError(err);
            return;
        }
        const data = this.collectData(action);
        this.postData(data);
        
    }
    /*----------------------------------------------------------------------------------------------------*/
    keyDown = (event) =>{
        // Ctrl + S hoặc Cmd + S (Mac)
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
            event.preventDefault();
            const btn = this.getSubmitButton("save");
            btn?.click();
            return;
        }
        if (event.key === "Enter"){
            const type = event.target.type;
            if (type !== "text" && type !== "password") return;
            /* Tìm submit control có thuộc tính data-default nếu không có thì tìm data-action = 'save'
             */
            const action = this.getDefaultAction() || "save";
            const btn = this.getSubmitButton(action);
            if (btn){
                /*chặn hành vi submit mặc định của form. Thường là nó submit form với
                submiter mặc định là input hoặc button đầu tiên có type = input*/
                event.preventDefault();
                btn.click();
            }
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    getSubmitButton(action){
        return this.frmControl.querySelector(
            `button[type="submit"][data-action="${action}"],
             input[type="submit"][data-action="${action}"]`
        );
    }
    /*----------------------------------------------------------------------------------------------------*/
    getDefaultAction(){
        const btn = this.frmControl.querySelector(
            'button[type="submit"][data-default], input[type="submit"][data-default]'
        );
        return btn?.dataset.action || null;
    }
    /*----------------------------------------------------------------------------------------------------*/
    async loadData(){
        //chưa có code
    };
    /*----------------------------------------------------------------------------------------------------*/
    async postData(data){
        const loading = new LoadingOverlay(this.frmControl,{"motion":"circle", "text":"loading", "textPosition":"center","size":10});
        loading.on();
        try{
            let response = await fetch(this.sUrlPost,{
                method : "POST",
                headers: {
                    "Accept": "application/json"   // expected data sent back
                },
                //body : JSON.stringify(data)
                body: new FormData(this.frmControl)
            });
            if(response.status !==200){
                throw Error (response.status + ". " + response.statusText);
            }
            let jsonResp = await response.json();
            this.render(jsonResp);
        }
        catch(error){
            console.log(error);
        }
        finally{
            loading.off();
        }
        
    }
    /*----------------------------------------------------------------------------------------------------*/
    render(jsonData){
        //chưa code
    }
    
    /*----------------------------------------------------------------------------------------------------*/
    constructor(frmControl,sUrlPost,options ={}){
        this.frmControl         = frmControl;
        this.sUrlPost           = sUrlPost;
        this.options            = options;
        this.initEvent();
    }
}