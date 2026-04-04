/*  Reference: https://stackoverflow.com/questions/29046635/javascript-es6-cross-browser-detection
 * https://anonystick.com/blog-developer/tong-hop-tinh-nang-javascript-moi-nhat-ke-tu-es6-den-es11-2019120577967801#t-14
 *  Return: true nếu tương thích ES6,ES7... false nếu ngược lại
 *  Hàm checkCrossBrowser nêy để sau thẻ BODY vì để test một số function CSS cần có phần tử BODY mới kiểm tra được
 */
function checkCrossBrowser() {
    "use strict";
    if (typeof Symbol == "undefined") return false;//kiểu dữ liệu Symbol có từ ES6
    try {
        /*
         Dùng eval là giải pháp an toàn để tránh lỗi cú pháp khi load script trên trình duyệt cũ — 
         nếu gõ trực tiếp mà browser không hiểu class, let, hoặc =>, nó sẽ báo syntax error trước khi chạy,
        tức là sẽ không chạy và go to catch(e) mà báo lỗi cú pháp tại đó
         */
        eval("class Foo {};const a=0;let bar = (x) => x+1;");//ES6: test xem có support  kiểu class và function arrow, khai báo let,cont 
        const numbers = [0,1];
        if (!numbers.includes(1)) { //ES7: test includes 
            return false;
        };
        eval("async function asyncCall(){}");//test async/await ES8
        // --- <script type="module"> ---
        const testScript = document.createElement("script");
        if (!("noModule" in testScript)) return false;
        
        if (
            !("HTMLScriptElement" in window) ||
            typeof HTMLScriptElement.supports !== "function" ||
            !HTMLScriptElement.supports("importmap")
        ) {
            return false;
        }
        
        //Begin test CSS supports
        if (!("CSS" in window) || !CSS.supports) return false;
        if(!CSS.supports("display", "flex") || !CSS.supports("display", "grid")){//test xem có support kiểu dữ liệu flex hoặc grid không
            return false;
        }
        document.querySelector(":scope");//test xem browser có support :scope selector không. Edge support từ 2020
        //End test CSS supports
        //Begin test clamp CSS function
        let divCheck =  document.createElement("DIV");
        let htmlBody = document.querySelector("BODY");
        divCheck.style.height = "clamp(10px,15px,20px)";
        htmlBody.appendChild(divCheck);
        if(divCheck.clientHeight !== 15){
            htmlBody.removeChild(divCheck);
            return false;
        }
        htmlBody.removeChild(divCheck);
        //End test clamp CSS function
    
    } catch (e) { 
        return false; 
    }
    return true;
};
/*----------------------------------------------------------------------------*/
function notifyIncompatibleBrowser() {
    document.documentElement.innerHTML = ""; // xoá nội dung cũ (nếu có)
    const overlay = document.createElement("div");
    overlay.style.cssText = `
        position: fixed;
        inset: 0;
        background: #fff;
        color: #222;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        font-family: system-ui, sans-serif;
        z-index: 999999;
        padding: 2rem;
    `;
    overlay.innerHTML = 
        `<h1 style="font-size:2rem; margin-bottom:1rem;">Trình duyệt của bạn không được hỗ trợ</h1>
        <p style="max-width:60rem; font-size:1rem; line-height:1.5;">
            Trang web này yêu cầu trình duyệt hiện đại hỗ trợ ES8+ và CSS3 (Flex, Grid, Clamp...).<br>
            Vui lòng nâng cấp lên phiên bản mới nhất của trình duyệt:
        </p>
        <div style="margin-top:1.5rem; display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
            <a href="https://www.google.com/chrome/" target="_blank"
            style="color:#fff; background:#4285F4; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
            Tải Chrome
            </a>
            <a href="https://www.microsoft.com/edge" target="_blank"
               style="color:#fff; background:#0078D7; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
               Tải Edge
            </a>
            <a href="https://www.mozilla.org/firefox/" target="_blank"
               style="color:#fff; background:#FF7139; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
               Tải Firefox
            </a>
            <a href="https://www.opera.com/download" target="_blank"
               style="color:#fff; background:#E60012; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
               Tải Opera
            </a>
            <a href="https://coccoc.com/download" target="_blank"
               style="color:#fff; background:#009A2E; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
               Tải Cốc Cốc
            </a>
            <a href="https://www.apple.com/safari/" target="_blank"
               style="color:#fff; background:#555; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none;">
               Tải Safari
            </a>
        </div>`;
    document.body.appendChild(overlay);
}
/*----------------------------------------------------------------------------*/
if (!checkCrossBrowser()) {
    //dùng javascript để hiển thị, không rederect ra trang lỗi PHP để giảm tải
    notifyIncompatibleBrowser();
}