function getInitials(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

export function UserAvatar({
  name,
  src,
  size = 'md',
}: {
  name: string;
  src?: string | null;
  size?: 'sm' | 'md' | 'lg';
}) {
  return (
    <span className={`user-avatar user-avatar-${size}${src ? ' user-avatar-photo' : ''}`} aria-hidden="true">
      {src ? <img src={src} alt="" /> : getInitials(name)}
    </span>
  );
}
