export function getCurrentUrl() {
    return window.location.href;
}
/*export function getFormSubmitUrl() {
    return window.location.origin + window.location.pathname;
}*/
/*thay thế action hiện tại trong url bằng action mới, có thể kèm theo param*/
export function buildActionUrl(
    strAction,
    objParam = {},
    strDefaultAction = 'index'
) {
    const url = new URL(window.location.href);

    let arrPath = url.pathname
        .split('/')
        .filter(Boolean);

    /*
     * Nếu action hiện tại được ghi tường minh là default action
     * thì bỏ nó đi trước khi gắn action mới.
     */
    if (arrPath.at(-1) === strDefaultAction) {
        arrPath.pop();
    }

    /*
     * Gắn action mới.
     */
    arrPath.push(strAction);

    url.pathname = '/' + arrPath.join('/');

    /*
     * Có thể bỏ toàn bộ query param cũ nếu URL mới
     * không nên kế thừa chúng.
     */
    url.search = '';

    for (const [strName, mixValue] of Object.entries(objParam)) {
        url.searchParams.set(
            strName,
            String(mixValue)
        );
    }

    return url.href;
}



