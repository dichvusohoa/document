export class LoadingOverlay{
    /*----------------------------------------------------------------------------------------------------*/
    constructor(htmlContainer, options ={}){
        if (!htmlContainer) {
            throw new Error("loadingOverlay: container is required");
        }
        this.htmlContainer = htmlContainer;
        this._setOptions(options);
        
        //tái sử dụng lại cấu trúc loading nếu đã được tạo từ trước và bị ẩn đi bởi lệnh off
        this.loadingElement = this._getLoadingElement();
        const element = this.htmlContainer.querySelector(".loading");
        //có .loading nhưng format không đúng hoặc không phù hợp
        if(element && !this.loadingElement){
            //code giúp phần clear remove phần tử element ra khỏi this.htmlContainer
            element.remove();
        }
    }
    /*----------------------------------------------------------------------------------------------------*/
    _setOptions(options){
        
        const spinner_opts = {
            text: "",             // nội dung text
            motion: "circle",    // "circle" | "bar"
            textPosition: "center", // "center" | "bottom" | "right"
            size: 4.8,
            segments: 16,
            dashUnits: 1,
            gapUnits: 2,
            period: 1
        };
        const bar_opts = {
            text: "",             // nội dung text
            motion: "bar",      // "circle" | "bar"
            textPosition: "bottom", // "center" | "bottom" | "right"
            size: 12.8, /*rem*/
            segments: 5,
            dashUnits: 1,
            gapUnits: 1,
            period: 1
        };
        let opts = {};
        if(!options.motion  || options.motion === "circle"){
            Object.assign(opts, spinner_opts, options);
        }
        else if(options.motion === "bar"){
            Object.assign(opts, bar_opts, options);
        }
       
        if(opts.motion === "circle"){
           
            opts.size = Math.max(opts.size, 1.6);
            opts.segments = Math.max(opts.segments, 16);
            opts.dashUnits = Math.max(opts.dashUnits, 1);
            opts.gapUnits = Math.max(opts.gapUnits, 1);
            opts.period = Math.max(opts.period, 1);
            opts.period = Math.min(opts.period, 5);
        }
        else if(opts.motion === "bar"){
           
            opts.size = Math.max(opts.size, 9.6);
            opts.segments = Math.max(opts.segments, 5);
            opts.dashUnits = Math.max(opts.dashUnits, 1);
            opts.gapUnits = Math.max(opts.gapUnits, 1);
            opts.period = Math.max(opts.period, 1);
            opts.period = Math.min(opts.period, 5);
        }
        this.options = opts;
    }
    /*----------------------------------------------------------------------------------------------------*/
    _getLoadingElement(){
        const loading = this.htmlContainer.querySelector(".loading");
        if(!loading){
            return null;
        }
        const svgMotion = loading.querySelector(".loading__motion");
        if(!svgMotion){
            return null;
        }
        if(!loading.querySelector(".loading__text")){
            return null;
        }
        const strMotion = this.options.motion;
        if(strMotion === "circle"){
            const track = svgMotion.querySelector('circle.spinner-track');
            const head  = svgMotion.querySelector('circle.spinner-head');
            if(!track || !head){
                return null;
            }

        }
        else if(strMotion === "bar"){
            const bar = svgMotion.querySelector('line.bar');
            if(!bar){
                return null;
            }
        }
        return loading;
    }
    /*----------------------------------------------------------------------------------------------------*/
    on(){
        // Kiểm tra div.loading
        if(this.loadingElement){
            this._updateElement();
        }
        else{
            this._createElement();
        }
        this._applyInteractionBlock();
    }
    /*----------------------------------------------------------------------------------------------------*/
    _setCssVariables(htmElement){
        htmElement.style.setProperty("--motion-size", `${this.options.size}rem`);
        htmElement.style.setProperty("--segments", `${this.options.segments}`);
        htmElement.style.setProperty("--dash-units", `${this.options.dashUnits}`);
        htmElement.style.setProperty("--gap-units", `${this.options.gapUnits}`);
        htmElement.style.setProperty("--period", `${this.options.period}s`);
        const pathLength = (this.options.dashUnits + this.options.gapUnits)*this.options.segments;
        /*phải thêm --path-length vì lý do hiện nay 2025 lệnh stroke-dashoffset không hoạt động chính xác
         * với calc, nên không thể đưa công thức quá phức tạp vào CSS stroke-dashoffset được
        */
        htmElement.style.setProperty("--path-length", pathLength);
        
    }
    /*----------------------------------------------------------------------------------------------------*/
    _createElement(){
        const loading = document.createElement("div");
        loading.className = this._className();
        this._setCssVariables(loading);
        // Spinner
        const strMotion = this.options.motion;
        let motion = null;
        if(strMotion === "circle"){
            motion = this._createSpinner();
        }
        else if(strMotion === "bar"){
            motion = this._createSlider();
        }
        loading.appendChild(motion);
        

        // Text. Luôn tạo phần tử loading__text để đề phòng người dùng tái sử dụng loading
        //nhiều lần với nhiều option khác nhau (có lúc có text có lúc không có text
        const textEl = document.createElement("div");
        textEl.className = "loading__text";
        if (this.options.textPosition !== "none" && this.options.text) {
            textEl.innerText = this.options.text;
        }
        else{
            textEl.style.display = "none";
        }
        loading.appendChild(textEl);
       

        this.htmlContainer.appendChild(loading);
        this.loadingElement = loading;
    }
    /*----------------------------------------------------------------------------------------------------*/
    _createSpinner() {
        const NS = "http://www.w3.org/2000/svg";//namespace
       
        const svg = document.createElementNS(NS, "svg");
        //thiết lập hệ tọa X=100, Y=100 thuận lợi cho các lệnh vẽ SVG cirlce, line độc lập
        //khỏi thiết bị
        svg.setAttribute("viewBox", "0 0 100 100");
        svg.classList.add("loading__motion");

        const track = document.createElementNS(NS, "circle");
        track.setAttribute("cx", "50");
        track.setAttribute("cy", "50");
        track.setAttribute("r", "40");
        const pathLength = (this.options.dashUnits + this.options.gapUnits)*this.options.segments;
        // set pathLength để tạo thuận lợi cho việc tính toán của các lệnh 
        // stroke-dasharray, stroke-dashoffset trong CSS
        track.setAttribute("pathLength", pathLength); 
        track.classList.add("spinner-track");

        const head = document.createElementNS(NS, "circle");
        head.setAttribute("cx", "50");
        head.setAttribute("cy", "50");
        head.setAttribute("r", "40");
        // set pathLength để tạo thuận lợi cho việc tính toán của các lệnh 
        // stroke-dasharray, stroke-dashoffset trong CSS
        head.setAttribute("pathLength", pathLength);
        head.classList.add("spinner-head");

        svg.append(track, head);
        return svg;
    }
    /*----------------------------------------------------------------------------------------------------*/
    _createSlider() {
        const NS = "http://www.w3.org/2000/svg";//namespace
       
        const svg = document.createElementNS(NS, "svg");
        svg.setAttribute("viewBox", "0 0 100 100");
        svg.classList.add("loading__motion");

        const bar = document.createElementNS(NS, "line");
        bar.setAttribute("x1", "0");
        bar.setAttribute("y1", "50");
        bar.setAttribute("x2", "100");
        bar.setAttribute("y2", "50");
        const pathLength = (this.options.dashUnits + this.options.gapUnits)*this.options.segments;
        bar.setAttribute("pathLength", pathLength);
        bar.classList.add("bar");
        
        svg.append(bar);
        return svg;
    }
    /*----------------------------------------------------------------------------------------------------*/
    _updateElement(){
        this.loadingElement.className = this._className();
        this._setCssVariables(this.loadingElement);
        const textEl = this.loadingElement.querySelector(".loading__text");
        if (this.options.textPosition === "none" || !this.options.text) {
            textEl.style.display = "none";
        } else {
            textEl.style.display = "";
            textEl.innerText = this.options.text;
        }
        this.loadingElement.style.display = "";
    }
    /*----------------------------------------------------------------------------------------------------*/
    _className(){
        return `loading loading--${this.options.motion} loading--text-${this.options.textPosition}`;
    }
    /*----------------------------------------------------------------------------------------------------*/
    _applyInteractionBlock(){
        this.loadingElement.style.pointerEvents = "all";
        this.htmlContainer.setAttribute("aria-busy", "true");
    }
    /*----------------------------------------------------------------------------------------------------*/
    _removeInteractionBlock(){
        this.loadingElement.style.pointerEvents = "none";
        this.htmlContainer.removeAttribute("aria-busy");
    }
    /*----------------------------------------------------------------------------------------------------*/
    off(){
        if (!this.loadingElement) return;
        this.loadingElement.style.display = "none";
        this._removeInteractionBlock();
    }
}