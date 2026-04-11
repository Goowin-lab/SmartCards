// src/components/Card.tsx
import React from "react";

type CardProps = {
  children?: React.ReactNode;
};

export default function Card({ children }: CardProps) {
  return (
    <div className="card">
      <div className="card-body">{children}</div>
    </div>
  );
}
