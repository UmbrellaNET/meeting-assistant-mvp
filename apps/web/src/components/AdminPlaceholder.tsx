export function AdminPlaceholder({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <div>
      <header className="page-header">
        <div>
          <h1>{title}</h1>
          <p>{description}</p>
        </div>
      </header>
      <section className="panel">
        <div className="empty-state">
          <h3>Not available yet</h3>
          <p>This admin area is not wired to the API yet.</p>
        </div>
      </section>
    </div>
  );
}
