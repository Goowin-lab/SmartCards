// src/services/auth.ts
import { api } from "./api";
import AsyncStorage from "@react-native-async-storage/async-storage";

// Respuesta esperada del endpoint JWT
type JwtResponse = {
  token: string;
  user_email: string;
  user_nicename: string;
  user_display_name: string;
};

// Login: pide token al backend y lo guarda en AsyncStorage
export async function loginWP(username: string, password: string) {
  const { data } = await api.post<JwtResponse>("/jwt-auth/v1/token", {
    username,
    password,
  });
  await AsyncStorage.setItem("@sc_token", data.token);
  return data;
}

// Logout: elimina el token guardado
export async function logoutWP() {
  await AsyncStorage.removeItem("@sc_token");
}
