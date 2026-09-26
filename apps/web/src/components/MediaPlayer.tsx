'use client';
import { useEffect, useRef, useState } from 'react';
import { api } from '@/lib/api';

export function MediaPlayer({meetingId,artifactId,type}:{meetingId:string;artifactId:string;type:'audio'|'video'}) {
  const mediaRef = useRef<HTMLMediaElement | null>(null);
  const [url,setUrl] = useState('');
  const [error,setError] = useState('');

  useEffect(()=>{
    api<{url:string}>(`/meetings/${meetingId}/artifacts/${artifactId}/playback`)
      .then(result=>setUrl(result.url))
      .catch(err=>setError(err instanceof Error ? err.message : 'Unable to load recording.'));
  },[meetingId,artifactId]);

  useEffect(()=>{
    const seek = (event: Event) => {
      const custom = event as CustomEvent<{ms:number}>;
      if (!mediaRef.current) return;
      mediaRef.current.currentTime = custom.detail.ms / 1000;
      void mediaRef.current.play();
    };
    window.addEventListener('meeting-seek',seek);
    return ()=>window.removeEventListener('meeting-seek',seek);
  },[]);

  if(error) return <div className="error-box">{error}</div>;
  if(!url) return <div className="media-loading">Preparing secure playback link...</div>;
  return <div className="media-player">
    {type==='video'
      ? <video ref={element=>{mediaRef.current=element;}} controls preload="metadata" src={url}/>
      : <audio ref={element=>{mediaRef.current=element;}} controls preload="metadata" src={url}/>
    }
  </div>;
}
