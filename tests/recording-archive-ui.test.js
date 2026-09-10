const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const elements = new Map();
function $(selector) {
    if (!elements.has(selector)) {
        const state = {props:{}, attrs:{}, classes:new Set(), value:''};
        const chain = new Proxy(state, {get(target, name) {
            if (name === 'prop') return (key,value) => {if(value===undefined)return target.props[key];target.props[key]=value;return chain;};
            if (name === 'attr') return (key,value) => {target.attrs[key]=value;return chain;};
            if (name === 'text') return value => {target.value=value;return chain;};
            if (name === 'addClass') return value => {target.classes.add(value);return chain;};
            if (name === 'removeClass') return value => {target.classes.delete(value);return chain;};
            if (name in target) return target[name];
            return () => chain;
        }});
        elements.set(selector,chain);
    }
    return elements.get(selector);
}
const context = { SemanticLocalization:{dataTableLocalisation:{}}, $, globalRootUrl:'/', globalTranslate:{}, window:{}, document:{}, console, Promise, Date, setTimeout:fn=>fn() };
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/assets/js/src/module-export-records-index.js','utf8')+'\nglobalThis.app=ModuleExtendedCDRs;',context);
(async () => {
    const app=context.app;
    app.getSearchText=()=>'{"employee":"204"}';
    let resolvePrepare, requests=0, transfers=0;
    app.archiveApi=()=>{requests++;return new Promise(resolve=>resolvePrepare=resolve);};
    app.transferRecordingArchive=()=>{transfers++;};
    const first=app.startRecordingArchive();
    const second=app.startRecordingArchive();
    assert.strictEqual(first,second,'double click shares one operation');
    assert.strictEqual($('#downloadRecords').props.disabled,true,'disabled immediately');
    await Promise.resolve();assert.strictEqual(requests,1,'one preparation request');
    resolvePrepare({state:'ready',id:'job',ticket:'ticket'});await first;
    assert.strictEqual(transfers,1);assert.strictEqual($('#downloadRecords').props.disabled,false);
    app.archiveApi=()=>Promise.reject(new Error('archive_has_no_valid_entries'));
    await app.startRecordingArchive();
    assert.match($('#recordingArchiveStatus').value,/No available recordings/);
    assert.strictEqual(app.archiveRequest,null,'error permits retry');
    const states=[{state:'building',id:'job',completed:1,total:2},{state:'ready',id:'job',ticket:'ticket'}];
    app.archiveApi=()=>Promise.resolve(states.shift());
    await app.startRecordingArchive();assert.strictEqual(transfers,2,'polling eventually downloads');
    console.log('recording-archive-ui: OK (double click, progress, errors, retry)');
})().catch(error=>{console.error(error);process.exitCode=1;});
