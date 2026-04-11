import { useState, type ReactNode } from "react";

type Props = {
  children: ReactNode;                 // <-- el texto/ícono dentro del botón
  initialVariant?: "primary" | "secondary"; // por si quieres cambiar el color inicial
};

export default function Button({ children, initialVariant = "primary" }: Props) {
  const [clicked, setClicked] = useState(false);

  const variant = clicked ? "secondary" : initialVariant;

  return (
    <button
      type="button"
      className={`btn btn-${variant}`}
      disabled={clicked}               // <-- no deja volver a cliquear
      onClick={() => setClicked(true)} // <-- al click se vuelve gris y se bloquea
    >
      {children}
    </button>
  );
}
