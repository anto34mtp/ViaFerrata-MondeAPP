import React from 'react';
import {StatusBar} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {AuthProvider} from './src/context/AuthContext';
import {LangProvider} from './src/context/LangContext';
import AppNavigator from './src/navigation/AppNavigator';

function App(): React.JSX.Element {
  return (
    <SafeAreaProvider>
      <LangProvider>
        <AuthProvider>
          <NavigationContainer>
            <StatusBar barStyle="light-content" backgroundColor="#2E7D32" />
            <AppNavigator />
          </NavigationContainer>
        </AuthProvider>
      </LangProvider>
    </SafeAreaProvider>
  );
}

export default App;
