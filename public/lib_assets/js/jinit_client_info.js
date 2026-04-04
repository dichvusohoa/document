async function initClientInfo(initialUri, postEndpoint) {
    const screen = {
        screenWidth: window.screen.width,
        screenHeight: window.screen.height,
        innerWidth: window.innerWidth,
        innerHeight: window.innerHeight,
        devicePixelRatio: window.devicePixelRatio || 1
    };
    const payload = {
        initial_uri: initialUri,
        screen: screen
    };
    try{
        let response = await fetch(postEndpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });
        if (!response.ok) {
            throw new Error(response.status + ". " + response.statusText);
        }
        let respData = await response.json();
        if(respData['status'] !== 'server_ok'){
            throw new Error('Server trả về báo lỗi trạng thái dữ liệu: ' + respData['status']);
        }
        window.location.href = initialUri;
    }
    catch(err){
        console.error(err);
    }    
}
