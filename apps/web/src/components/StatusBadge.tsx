export function StatusBadge({value}:{value:string}) { const normalized=value?.replaceAll('_',' ')||'unknown'; return <span className={`status status-${value}`}>{normalized}</span>; }
