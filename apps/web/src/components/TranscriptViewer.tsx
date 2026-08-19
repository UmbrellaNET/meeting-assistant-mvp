'use client';
import { useState } from 'react';
import { api, formatTime } from '@/lib/api';
import type { Segment } from '@/lib/types';

export function TranscriptViewer({meetingId,initialSegments}:{meetingId:string;initialSegments:Segment[]}){
  const [segments,setSegments]=useState(initialSegments); const [editing,setEditing]=useState<string|null>(null); const [name,setName]=useState('');
  const save=async(segment:Segment)=>{ if(!segment.speaker) return; const updated=await api(`/meetings/${meetingId}/speakers/${segment.speaker.id}`,{method:'PATCH',body:JSON.stringify({display_name:name})}) as {display_name:string}; setSegments(current=>current.map(item=>item.speaker?.id===segment.speaker?.id?{...item,speaker:{...item.speaker!,display_name:updated.display_name,identity_status:'confirmed'}}:item)); setEditing(null); };
  return <div className="transcript-list">{segments.map(segment=><article className="transcript-segment" key={segment.id}>
    <button className="timestamp" title="Playback integration point" onClick={()=>window.dispatchEvent(new CustomEvent('meeting-seek',{detail:{ms:segment.start_ms}}))}>{formatTime(segment.start_ms)}</button>
    <div className="segment-body"><div className="speaker-row">
      {editing===segment.speaker?.id?<><input value={name} onChange={e=>setName(e.target.value)} autoFocus/><button className="small-button" onClick={()=>void save(segment)}>Save</button></>:<button className="speaker-name" onClick={()=>{setEditing(segment.speaker?.id??null);setName(segment.speaker?.display_name??'Speaker');}}>{segment.speaker?.display_name??'Unknown speaker'}</button>}
      <span className="identity-state">{segment.speaker?.identity_status}</span>
    </div><p>{segment.text}</p></div>
  </article>)}</div>;
}
