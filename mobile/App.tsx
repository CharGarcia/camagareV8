import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider } from './src/auth/AuthContext';
import { SerieProvider } from './src/pedidos/SerieContext';
import RootNavigator from './src/navigation/RootNavigator';

export default function App() {
  return (
    <AuthProvider>
      <SerieProvider>
        <StatusBar style="light" />
        <RootNavigator />
      </SerieProvider>
    </AuthProvider>
  );
}
