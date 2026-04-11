// src/services/credits.ts
import { api } from "./api";

// Estructura de respuesta del endpoint de créditos
export type CreditsResponse = {
  user_id: number;
  credits: number;
  used: number;
  updated_at: string | null;
};

// Lee los créditos del usuario autenticado (usa Bearer del interceptor)
export async function getMyCredits() {
  const { data } = await api.get<CreditsResponse>("/smartcards/v1/credits/me");
  return data;
}

// (Opcional admin) Lee los créditos de un usuario por ID
export async function getCreditsById(userId: number) {
  const { data } = await api.get<CreditsResponse>(`/smartcards/v1/credits/${userId}`);
  return data;
}

// (Admin) Modifica créditos: set | add | sub
export async function mutateCredits(
  userId: number,
  op: "set" | "add" | "sub",
  value: number
) {
  const { data } = await api.post<CreditsResponse>(`/smartcards/v1/credits/${userId}`, {
    op,
    value,
  });
  return data;
}
