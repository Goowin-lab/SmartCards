import { useEffect, useState } from "react";
import { getCredits, type CreditsResponse } from "./services/credits";

export default function App() {
  const [data, setData] = useState<CreditsResponse | null>(null);
  const [err, setErr] = useState<any>(null);

  useEffect(() => {
    getCredits(6) // <-- usa un userId real
      .then(setData)
      .catch((e) => setErr(e?.response?.data || e.message));
  }, []);

  return (
    <div style={{ padding: 24 }}>
      <h1>SmartCards</h1>
      <pre>DATA: {JSON.stringify(data, null, 2)}</pre>
      <pre>ERROR: {JSON.stringify(err, null, 2)}</pre>
    </div>
  );
}
