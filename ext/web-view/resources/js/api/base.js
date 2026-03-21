export const readStorage = (key) => {
    if (typeof window === 'undefined') {
        return '';
    }

    return window.localStorage.getItem(key) ?? '';
};

export const writeStorage = (key, value) => {
    if (typeof window === 'undefined' || !value) {
        return;
    }

    window.localStorage.setItem(key, value);
};
